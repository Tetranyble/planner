<?php

namespace App\Repository\Eloquent;

use App\Enums\Connection\ProviderConnectionStatus;
use App\Enums\Connection\SocialAccountStatus;
use App\Models\AnalyticRollup;
use App\Models\AnalyticSnapshot;
use App\Models\ApiLog;
use App\Models\ContentTarget;
use App\Models\IntegrationSyncCursor;
use App\Models\MetaDataDeletionRequest;
use App\Models\MetaDataDeletionTarget;
use App\Models\ProviderConnection;
use App\Models\PublishedPost;
use App\Models\SocialAccount;
use App\Models\SocialAccountCapability;
use App\Models\SocialAccountStatusEvent;
use App\Models\SocialOAuthSelection;
use App\Models\WebhookEvent;
use App\Repository\Contracts\MetaDataDeletionRepositoryContract;
use App\Repository\Contracts\ProviderCredentialRepositoryContract;
use App\Repository\Contracts\ProviderGrantOwnershipRepositoryContract;
use DateTimeInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** A provider-initiated privacy request crosses tenants: never use a browser workspace scope here. */
final class MetaDataDeletionRepository implements MetaDataDeletionRepositoryContract
{
    public function __construct(private readonly ProviderCredentialRepositoryContract $credentials,
        private readonly ProviderGrantOwnershipRepositoryContract $ownership) {}

    private function actorDigest(string $actor): string
    {
        $secret = (string) config('social_integrations.meta_actor_hmac_key', '');
        if ($secret === '') {
            throw new \LogicException('Stable privacy HMAC key is required for deletion actor identifiers.');
        }

        return hash_hmac('sha256', $actor, $secret);
    }

    public function blocksExistingOAuth(string $actorId, DateTimeInterface $oauthStartedAt): bool
    {
        return MetaDataDeletionRequest::query()->where('actor_digest', $this->actorDigest($actorId))
            ->where('created_at', '>=', $oauthStartedAt)
            ->exists()
            || MetaDataDeletionRequest::query()->where('actor_digest', $this->actorDigest($actorId))
                ->whereIn('state', ['PENDING', 'PROCESSING', 'FAILED', 'NEEDS_REVIEW'])->exists();
    }

    public function accept(string $actorId, string $signedRequestDigest): MetaDataDeletionRequest
    {
        return DB::transaction(function () use ($actorId, $signedRequestDigest): MetaDataDeletionRequest {
            $row = MetaDataDeletionRequest::query()->where('signed_request_digest', $signedRequestDigest)
                ->lockForUpdate()->first();
            if ($row !== null) {
                return $row;
            }
            $row = MetaDataDeletionRequest::query()->create([
                'signed_request_digest' => $signedRequestDigest,
                'actor_digest' => $this->actorDigest($actorId),
                'actor_ciphertext' => Crypt::encryptString($actorId),
                'confirmation_code' => Str::random(48),
                'state' => 'PENDING',
                'accepted_at' => now(),
            ]);
            // Meta user IDs are app-scoped; this must search *all* organisations and both
            // Facebook and Instagram. Page or Instagram business IDs are NOT identities here.
            $linkedIds = $this->ownership->metaConnectionIds($actorId);
            $connections = ProviderConnection::query()->whereIn('provider', ['facebook', 'instagram'])
                ->where(function ($query) use ($actorId, $linkedIds): void {
                    $query->where('external_actor_id', $actorId);
                    if ($linkedIds !== []) {
                        $query->orWhereIn('id', $linkedIds);
                    }
                })->lockForUpdate()->get();
            foreach ($connections as $connection) {
                MetaDataDeletionTarget::query()->create([
                    'request_id' => $row->id, 'organisation_id' => $connection->organisation_id,
                    'provider_connection_id' => $connection->id, 'state' => 'PENDING',
                ]);
                // Immediately remove reusable credentials and suspend every account using this grant.
                $this->credentials->erase($connection);
                $connection->forceFill([
                    'status' => ProviderConnectionStatus::REVOKED->value,
                    'reconnect_reason' => 'META_DATA_DELETION',
                    'access_token_secret_ref' => 'erased',
                    'refresh_token_secret_ref' => null,
                    'token_expires_at' => null,
                    'refresh_lease_token' => null, 'refresh_lease_until' => null,
                    'refresh_retry_at' => null,
                ])->save();
                $accountIds = SocialAccount::query()->where('organisation_id', $connection->organisation_id)
                    ->where('provider_connection_id', $connection->id)->pluck('id')->all();
                if ($accountIds !== []) {
                    SocialAccount::query()->whereIn('id', $accountIds)
                        ->update(['status' => SocialAccountStatus::RESTRICTED->value, 'updated_at' => now()]);
                    SocialAccountCapability::query()->whereIn('social_account_id', $accountIds)
                        ->update(['mode' => 'UNAVAILABLE', 'reason' => 'Provider privacy request received', 'checked_at' => now()]);
                }
            }
            $this->cancelOutstandingSelections($actorId);
            $row->forceFill(['matched_grants' => $connections->count()])->save();
            if ($connections->isEmpty()) {
                $row->forceFill([
                    'state' => 'COMPLETED', 'completed_at' => now(), 'actor_ciphertext' => null,
                ])->save();
            }

            return $row;
        }, attempts: 3);
    }

    /** Remove short-lived unclaimed Meta grants for the same app-scoped user. */
    private function cancelOutstandingSelections(string $actorId): void
    {
        SocialOAuthSelection::query()->whereIn('provider', ['facebook', 'instagram'])
            ->where(fn ($query) => $query->where('meta_actor_digest', $this->actorDigest($actorId))
                ->orWhereNull('meta_actor_digest'))
            ->whereNull('consumed_at')->where('expires_at', '>', now())
            ->orderBy('id')->chunkById(100, function ($selections) use ($actorId): void {
                foreach ($selections as $selection) {
                    try {
                        $grant = json_decode(Crypt::decryptString($selection->grant_ciphertext), true, 16, JSON_THROW_ON_ERROR);
                    } catch (\Throwable) {
                        // Corrupt grants must not be used again, regardless of actor.
                        $grant = null;
                    }
                    if ($grant === null || ($grant['actorId'] ?? null) === $actorId) {
                        $selection->forceFill([
                            'consumed_at' => now(),
                            'grant_ciphertext' => Crypt::encryptString('purged'),
                            'candidates_ciphertext' => Crypt::encryptString('purged'),
                        ])->save();
                    }
                }
            });
    }

    public function findPublic(string $code): ?MetaDataDeletionRequest
    {
        if (! preg_match('/^[A-Za-z0-9]{48}$/D', $code)) {
            return null;
        }

        return MetaDataDeletionRequest::query()->where('confirmation_code', $code)->first();
    }

    public function dueIds(int $limit): array
    {
        return MetaDataDeletionRequest::query()->whereIn('state', ['PENDING', 'FAILED'])
            ->where('attempts', '<', 3)->orderBy('created_at')->limit(max(1, min(100, $limit)))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function process(int $requestId): void
    {
        DB::transaction(function () use ($requestId): void {
            $request = MetaDataDeletionRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();
            if (! in_array($request->state, ['PENDING', 'FAILED'], true)) {
                return;
            }
            $request->forceFill(['state' => 'PROCESSING', 'attempts' => (int) $request->attempts + 1])->save();
            $requiresReview = false;
            foreach (MetaDataDeletionTarget::query()->where('request_id', $request->id)
                ->lockForUpdate()->get() as $target) {
                if ($target->state === 'COMPLETED') {
                    continue;
                }
                $connection = ProviderConnection::query()->where('organisation_id', $target->organisation_id)
                    ->whereKey($target->provider_connection_id)->lockForUpdate()->firstOrFail();
                if ($connection->status === ProviderConnectionStatus::CONNECTED->value) {
                    $target->forceFill(['state' => 'NEEDS_REVIEW', 'review_reason' => 'NEW_GRANT_CONFLICT'])->save();
                    $requiresReview = true;

                    continue;
                }
                $accounts = SocialAccount::query()->where('organisation_id', $target->organisation_id)
                    ->where('provider_connection_id', $connection->id)->lockForUpdate()->get();
                $ids = $accounts->pluck('id')->all();
                $contentNeedsReview = $ids !== [] && (
                    PublishedPost::query()->whereIn('social_account_id', $ids)->exists()
                    || ContentTarget::query()->whereIn('social_account_id', $ids)->exists()
                    || WebhookEvent::query()->where('organisation_id', $connection->organisation_id)
                        ->whereIn('provider', ['facebook', 'instagram'])->whereNull('social_account_id')->exists()
                );
                if ($ids !== []) {
                    // These are provider-derived snapshots, webhooks and sync state, not
                    // brand-authored campaigns or immutable compliance audit records.
                    AnalyticSnapshot::query()->whereIn('social_account_id', $ids)->delete();
                    AnalyticRollup::query()->whereIn('social_account_id', $ids)->delete();
                    IntegrationSyncCursor::query()->whereIn('social_account_id', $ids)->delete();
                    WebhookEvent::query()->whereIn('social_account_id', $ids)->delete();
                    ApiLog::query()->whereIn('social_account_id', $ids)->delete();
                    SocialAccountStatusEvent::query()->whereIn('social_account_id', $ids)->update(['details' => null]);
                }
                foreach ($accounts as $account) {
                    $account->forceFill([
                        'status' => SocialAccountStatus::ARCHIVED->value,
                        'external_account_id' => 'erased:'.$account->uuid,
                        'display_name' => 'Removed provider account', 'handle' => null, 'last_sync_at' => null,
                    ])->save();
                }
                $connection->forceFill([
                    'external_actor_id' => 'erased:'.$connection->uuid,
                    'access_token_secret_ref' => 'erased', 'refresh_token_secret_ref' => null,
                    'scopes' => [], 'token_expires_at' => null, 'last_verified_at' => null,
                    'status' => ProviderConnectionStatus::REVOKED->value,
                    'reconnect_reason' => 'META_DATA_DELETION',
                ])->save();
                // Refuse to assert completion for brand-authored content that could
                // still contain user data. An operator must review its retention basis.
                $target->forceFill([
                    'state' => $contentNeedsReview ? 'NEEDS_REVIEW' : 'COMPLETED',
                    'review_reason' => $contentNeedsReview ? 'BRAND_CONTENT_REVIEW' : null,
                    'processed_at' => now(),
                ])->save();
                $requiresReview = $requiresReview || $contentNeedsReview;
            }
            $processed = MetaDataDeletionTarget::query()->where('request_id', $request->id)
                ->whereIn('state', ['COMPLETED', 'NEEDS_REVIEW'])->count();
            $request->forceFill([
                'processed_grants' => $processed,
                'state' => $requiresReview ? 'NEEDS_REVIEW' : 'COMPLETED',
                'last_error_code' => $requiresReview ? 'RETENTION_REVIEW_REQUIRED' : null,
                'completed_at' => $requiresReview ? null : now(),
                'actor_ciphertext' => null,
            ])->save();
        }, attempts: 3);
    }

    public function fail(int $requestId, string $code): void
    {
        DB::transaction(function () use ($requestId, $code): void {
            $request = MetaDataDeletionRequest::query()->whereKey($requestId)->lockForUpdate()->first();
            if ($request === null || ! in_array($request->state, ['PENDING', 'PROCESSING', 'FAILED'], true)) {
                return;
            }
            $exhausted = (int) $request->attempts >= 3;
            $request->forceFill([
                'state' => $exhausted ? 'NEEDS_REVIEW' : 'FAILED',
                'last_error_code' => $exhausted ? 'PROCESSING_RETRIES_EXHAUSTED' : $code,
                'actor_ciphertext' => null,
            ])->save();
        }, attempts: 3);
    }
}
