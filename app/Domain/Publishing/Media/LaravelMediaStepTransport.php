<?php

namespace App\Domain\Publishing\Media;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Unregistered transport. Future worker must supply a leased, approved request. */
final class LaravelMediaStepTransport implements MediaStepTransportContract
{
    public function send(MediaStepRequest $request, string $accessToken): array
    {
        MediaStepEgressPolicy::assertAllowed($request);
        // A direct transport invocation must not bypass the global release gate.
        if (! (bool) config('planner.media_dispatch_released', false) || ! app()->environment('production')) {
            throw new \DomainException('Media transport is not released.');
        }
        try {
            $client = Http::withToken($accessToken)->withHeaders($request->headers)
                ->withOptions(['allow_redirects' => false, 'http_errors' => false])
                ->connectTimeout(5)->timeout(15);
            $reply = $request->method === 'GET'
                ? $client->get($request->url, $request->body)
                : $client->post($request->url, $request->body);
            $raw = $reply->json();
            if (! is_array($raw)) {
                $raw = [];
            }

            return ['status' => $reply->status(), 'may_have_sent' => true,
                'body' => [
                    'id' => isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : null,
                    'status_code' => $raw['status_code'] ?? null,
                    'owner' => ['id' => is_string($raw['owner']['id'] ?? null) ? $raw['owner']['id'] : null],
                    'data' => ['publish_id' => $raw['data']['publish_id'] ?? null,
                        'status' => $raw['data']['status'] ?? null,
                        'publicaly_available_post_id' => $raw['data']['publicaly_available_post_id'] ?? []],
                    'error' => ['code' => $raw['error']['code'] ?? null],
                ]];
        } catch (ConnectionException) {
            return ['status' => null, 'body' => [], 'may_have_sent' => true];
        }
    }
}
