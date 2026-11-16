<?php

namespace App\Domain\Publishing\Media;

/** No remote HTTP without all independent authorization facts and explicitly released deployment. */
final readonly class GuardedMediaStep
{
    public function __construct(private MediaStepTransportContract $transport) {}

    public function execute(MediaStepRequest $request, MediaStepAuthorization $authorization, string $token): array
    {
        MediaStepEgressPolicy::assertAllowed($request);
        // YouTube requires a vetted Storage streaming session and resumable Location handling;
        // never send an incomplete initialization that cannot be continued safely.
        if ($request->provider === 'youtube') {
            return ['state' => 'BLOCKED', 'code' => 'YOUTUBE_RESUMABLE_STREAM_NOT_AVAILABLE'];
        }
        if (! $authorization->allowed() || ! (bool) config('planner.media_dispatch_released', false)
            || ! app()->environment('production')) {
            return ['state' => 'BLOCKED', 'code' => 'MEDIA_DISPATCH_NOT_AUTHORIZED'];
        }
        if ($token === '' || strlen($token) > 8192 || preg_match('/[\r\n\x00]/', $token)) {
            return ['state' => 'BLOCKED', 'code' => 'INVALID_CREDENTIAL'];
        }
        $result = $this->transport->send($request, $token);
        $http = $result['status'] ?? null;
        if ($http === null) {
            return ['state' => 'UNCERTAIN', 'code' => 'REMOTE_RESPONSE_UNKNOWN'];
        }
        if ($http === 429) {
            return ['state' => 'UNCERTAIN', 'code' => 'RATE_LIMIT_AFTER_ATTEMPT'];
        }
        if ($http >= 500 || ($http >= 300 && $http < 400)) {
            return ['state' => 'UNCERTAIN', 'code' => 'PROVIDER_RESPONSE_AMBIGUOUS'];
        }
        if ($http < 200 || $http >= 300) {
            return ['state' => 'FAILED', 'code' => 'PROVIDER_REJECTED'];
        }
        $interpreted = MediaPublicationProtocols::providerState($request->provider, $request->operation,
            is_array($result['body'] ?? null) ? $result['body'] : []);

        // No raw provider bodies, upload endpoints or tokens leave this guard.
        return ['state' => $interpreted['state'], 'code' => $interpreted['code'],
            'provider_receipt' => $interpreted['receipt'] ?? null,
            'provider_owner_id' => $interpreted['owner_id'] ?? null];
    }
}
