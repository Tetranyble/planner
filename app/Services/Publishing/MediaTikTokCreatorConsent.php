<?php

namespace App\Services\Publishing;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\ContentTarget;
use App\Models\MediaPublicationPreparation;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\PublicationJob;
use App\Models\User;
use App\Repository\Contracts\MediaPublicationRunRepositoryContract;
use App\Repository\Contracts\MediaTikTokConsentRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Current creator options come from TikTok, never a browser-supplied list. */
final class MediaTikTokCreatorConsent
{
    public function __construct(
        private readonly BrandPermissionGuard $guard,
        private readonly MediaPublicationRunRepositoryContract $runs,
        private readonly MediaTikTokConsentRepositoryContract $consents,
    ) {}

    private function authorize(User $actor, Organisation $org, Brand $brand): void
    {
        if ((int) $brand->organisation_id !== (int) $org->id
            || ! $this->guard->allows($actor, $brand, 'approval_request.update')
            || ! $this->guard->allows($actor, $brand, 'social-account.connect')) {
            throw new AuthorizationException('TikTok posting consent requires active manager access.');
        }
    }

    private function context(User $actor, Organisation $org, Brand $brand, int $runId): array
    {
        $this->authorize($actor, $org, $brand);
        $run = $this->runs->find($org, $brand, $runId);
        if ($run->provider !== 'tiktok' || $run->state !== 'HELD') {
            throw new ConflictHttpException('Creator consent requires a held TikTok run.');
        }
        $prep = MediaPublicationPreparation::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($run->media_publication_preparation_id)->firstOrFail();
        $job = PublicationJob::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($run->publication_job_id)->firstOrFail();
        if ($prep->state !== 'PREPARED' || $job->state !== 'AWAITING_USER' || (int) $job->attempt_count !== 0) {
            throw new ConflictHttpException('TikTok preparation or publication changed.');
        }
        $target = ContentTarget::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($job->content_target_id)->with('account')->firstOrFail();
        $account = $target->account;
        if (! $account || $account->provider !== 'tiktok' || $account->status !== 'ACTIVE') {
            throw new ConflictHttpException('TikTok account is unavailable.');
        }
        $grant = ProviderConnection::query()->where('organisation_id', $org->id)
            ->whereKey($account->provider_connection_id)->where('provider', 'tiktok')->where('status', 'CONNECTED')->first();
        if (! $grant || ! $grant->token_expires_at || $grant->token_expires_at->lessThanOrEqualTo(now()->addMinutes(5))
            || ! in_array('video.publish', is_array($grant->scopes) ? $grant->scopes : [], true)) {
            throw new ConflictHttpException('TikTok creator grant lacks active video.publish authorization.');
        }
        $credential = ProviderConnectionCredential::query()->where('organisation_id', $org->id)
            ->where('provider_connection_id', $grant->id)->first();
        if (! $credential?->access_token_ciphertext) {
            throw new ConflictHttpException('TikTok token unavailable.');
        }
        $token = Crypt::decryptString($credential->access_token_ciphertext);
        if ($token === '' || strlen($token) > 8192) {
            throw new ConflictHttpException('TikTok token unavailable.');
        }
        if (! is_string(config('app.key')) || config('app.key') === '') {
            throw new ConflictHttpException('TikTok consent signing key is unavailable.');
        }
        $fingerprint = hash_hmac('sha256', $token, (string) config('app.key'));

        return compact('run', 'prep', 'job', 'target', 'token', 'fingerprint');
    }

    public function options(User $actor, Organisation $org, Brand $brand, int $runId): array
    {
        $ctx = $this->context($actor, $org, $brand, $runId);

        return $this->fetchOptions($ctx, $runId);
    }

    private function fetchOptions(array $ctx, int $runId): array
    {
        $response = Http::withToken($ctx['token'])->acceptJson()->withOptions(['allow_redirects' => false, 'http_errors' => false])
            ->connectTimeout(5)->timeout(10)
            ->post('https://open.tiktokapis.com/v2/post/publish/creator_info/query/', []);
        $body = $response->json();
        if ($response->status() !== 200 || ! is_array($body) || ($body['error']['code'] ?? null) !== 'ok'
            || ! is_array($body['data']['privacy_level_options'] ?? null)) {
            throw new ConflictHttpException('TikTok creator options could not be verified.');
        }
        $allowed = ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'];
        $levels = array_values(array_unique(array_filter($body['data']['privacy_level_options'],
            fn ($x) => is_string($x) && in_array($x, $allowed, true))));
        if ($levels === []) {
            throw new ConflictHttpException('TikTok did not return usable privacy options.');
        }
        $data = $body['data'];

        return ['run_id' => $runId, 'privacy_levels' => $levels,
            'comment_disabled' => ($data['comment_disabled'] ?? false) === true,
            'duet_disabled' => ($data['duet_disabled'] ?? false) === true,
            'stitch_disabled' => ($data['stitch_disabled'] ?? false) === true,
            'checked_at' => now()->toIso8601String(), 'posting_enabled' => false];
    }

    private function aiGeneratedForRun(Organisation $org, Brand $brand, int $runId): bool
    {
        $run = $this->runs->find($org, $brand, $runId);
        $job = PublicationJob::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($run->publication_job_id)->firstOrFail();
        $target = ContentTarget::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($job->content_target_id)->with('version')->firstOrFail();

        return (bool) $target->version?->ai_generated;
    }

    public function consent(User $actor, Organisation $org, Brand $brand, int $runId, array $choice): array
    {
        if (($choice['explicit_consent'] ?? false) !== true) {
            throw new ConflictHttpException('Explicit consent is required for this specific TikTok video.');
        }
        // Deliberately query TikTok AGAIN at consent time; clients cannot supply allowed values.
        $initial = $this->context($actor, $org, $brand, $runId);
        $verified = $this->fetchOptions($initial, $runId);
        $privacy = $choice['privacy_level'] ?? null;
        if (! is_string($privacy) || ! in_array($privacy, $verified['privacy_levels'], true)) {
            throw new ConflictHttpException('Selected privacy is not among current creator options.');
        }
        foreach (['comment', 'duet', 'stitch'] as $kind) {
            if ($verified[$kind.'_disabled'] && ! ($choice['disable_'.$kind] ?? false)) {
                throw new ConflictHttpException('Selected interaction settings conflict with TikTok creator limits.');
            }
        }
        if ($this->aiGeneratedForRun($org, $brand, $runId) && ! ($choice['is_aigc'] ?? false)) {
            throw new ConflictHttpException('AI-generated content requires the TikTok AI disclosure flag.');
        }
        $selection = ['privacy_level' => $privacy,
            'disable_comment' => (bool) ($choice['disable_comment'] ?? false),
            'disable_duet' => (bool) ($choice['disable_duet'] ?? false),
            'disable_stitch' => (bool) ($choice['disable_stitch'] ?? false),
            'is_aigc' => (bool) ($choice['is_aigc'] ?? false),
            'explicit_consent' => true];

        return DB::transaction(function () use ($actor, $org, $brand, $runId, $selection, $verified, $initial) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $lockedBrand = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)
                ->lockForUpdate()->firstOrFail();
            $ctx = $this->context($actor, $org, $lockedBrand, $runId);
            if (! hash_equals($initial['fingerprint'], $ctx['fingerprint'])) {
                throw new ConflictHttpException('TikTok credentials rotated during consent; recheck creator options.');
            }
            $record = $this->consents->create($org, $lockedBrand, $ctx['run'], $actor, $selection, $verified,
                $ctx['fingerprint'], (string) $ctx['prep']->approval_payload_hash, (string) $ctx['prep']->media_sha256);

            return ['id' => (int) $record->id, 'run_id' => (int) $ctx['run']->id,
                'privacy_level' => $selection['privacy_level'], 'consented' => true,
                'expires_at' => $record->verified_at->addMinutes(10)->toIso8601String(),
                'live_publishing_enabled' => false];
        }, 3);
    }
}
