<?php

namespace App\Repository\Eloquent;

use App\Enums\Connection\ProviderConnectionStatus;
use App\Enums\Connection\SocialAccountStatus;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\ProviderGrantLink;
use App\Models\ProviderGrantRevocationRequest;
use App\Models\SocialAccount;
use App\Models\SocialAccountCapability;
use App\Models\SocialAccountStatusEvent;
use App\Repository\Contracts\ProviderCredentialRepositoryContract;
use App\Repository\Contracts\ProviderTokenLifecycleRepositoryContract;
use App\Social\Data\OAuthGrant;
use App\Social\Data\TokenRefreshLease;
use App\Social\Data\TokenRefreshResult;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** MySQL-backed refresh lease and credential transition repository; provider HTTP stays in service. */
final class ProviderTokenLifecycleRepository implements ProviderTokenLifecycleRepositoryContract
{
    public function __construct(private readonly ProviderCredentialRepositoryContract $credentials) {}

    public function dueIds(int $limit, int $leadMinutes): array
    {
        $limit = max(1, min(100, $limit));
        $leadMinutes = max(1, min(120, $leadMinutes));

        return ProviderConnection::query()
            ->where('status', ProviderConnectionStatus::CONNECTED->value)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addMinutes($leadMinutes))
            ->where(fn ($q) => $q->whereNull('refresh_retry_at')->orWhere('refresh_retry_at', '<=', now()))
            ->orderBy('token_expires_at')->limit($limit)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function acquire(int $connectionId, int $leadMinutes): ?TokenRefreshLease
    {
        return DB::transaction(function () use ($connectionId, $leadMinutes): ?TokenRefreshLease {
            $connection = ProviderConnection::query()->whereKey($connectionId)->lockForUpdate()->first();
            if ($connection === null || $connection->status !== ProviderConnectionStatus::CONNECTED->value
                || $connection->token_expires_at === null
                || $connection->token_expires_at->gt(now()->addMinutes(max(1, min(120, $leadMinutes))))) {
                return null;
            }
            if ($connection->refresh_retry_at !== null && $connection->refresh_retry_at->isFuture()) {
                return null;
            }
            // Never rotate credentials for a grant that has been confirmed for revocation.
            $linked = ProviderGrantLink::query()
                ->where('provider_connection_id', $connection->id)->whereNull('detached_at')->first();
            if ($linked && ProviderGrantRevocationRequest::query()
                ->where('grant_group_id', $linked->grant_group_id)
                ->whereIn('status', ['READY', 'IN_FLIGHT', 'UNCERTAIN'])->exists()) {
                return null;
            }

            // A stale lease may represent a provider-issued *rotated* refresh token that we
            // failed to persist (process crashed/timeout). Never replay it automatically.
            if ($connection->refresh_lease_token !== null) {
                if ($connection->refresh_lease_until !== null && $connection->refresh_lease_until->isFuture()) {
                    return null;
                }
                $this->markReauthorization($connection, 'REFRESH_OUTCOME_UNKNOWN');

                return null;
            }

            $credential = ProviderConnectionCredential::query()
                ->where('organisation_id', $connection->organisation_id)
                ->where('provider_connection_id', $connection->id)->lockForUpdate()->first();
            if ($credential === null || $credential->refresh_token_ciphertext === null
                || ($credential->refresh_token_expires_at !== null && $credential->refresh_token_expires_at->isPast())) {
                $this->markReauthorization($connection, 'NO_VALID_REFRESH_TOKEN');

                return null;
            }
            try {
                $refreshToken = Crypt::decryptString($credential->refresh_token_ciphertext);
            } catch (\Throwable) {
                $this->markReauthorization($connection, 'CREDENTIAL_UNREADABLE');

                return null;
            }
            $leaseToken = (string) Str::uuid();
            $connection->forceFill([
                'refresh_lease_token' => $leaseToken,
                'refresh_lease_until' => now()->addSeconds(90),
                'refresh_attempts' => (int) $connection->refresh_attempts + 1,
                'last_refresh_result' => 'IN_PROGRESS',
            ])->save();

            return new TokenRefreshLease((int) $connection->id, $connection->provider, $leaseToken, $refreshToken);
        }, attempts: 3);
    }

    public function complete(TokenRefreshLease $lease, TokenRefreshResult $result): bool
    {
        return DB::transaction(function () use ($lease, $result): bool {
            $connection = $this->lockMatching($lease);
            if ($connection === null) {
                return false;
            }
            if ($result->actorId !== null && $result->actorId !== (string) $connection->external_actor_id) {
                $this->markReauthorization($connection, 'PROVIDER_IDENTITY_CHANGED');

                return false;
            }
            $oldScopes = is_array($connection->scopes) ? $connection->scopes : [];
            $newScopes = $result->scopes ?? $oldScopes;
            if (array_diff($oldScopes, $newScopes) !== []) {
                $this->markReauthorization($connection, 'PROVIDER_SCOPE_REDUCED');

                return false;
            }
            // Access and rotated refresh credentials are persisted together, in one transaction.
            $grant = new OAuthGrant($result->accessToken, $result->refreshToken,
                (string) $connection->external_actor_id, $newScopes, $result->expiresIn, $result->refreshExpiresIn);
            $this->credentials->store($connection, $grant);
            $connection->forceFill([
                'scopes' => $newScopes,
                'token_expires_at' => now()->addSeconds($result->expiresIn),
                'last_refresh_at' => now(),
                'last_refresh_result' => 'SUCCEEDED',
                'refresh_retry_at' => null,
                'refresh_lease_token' => null, 'refresh_lease_until' => null,
                'reconnect_reason' => null,
            ])->save();

            return true;
        }, attempts: 3);
    }

    public function fail(TokenRefreshLease $lease, string $safeCode, bool $retrySafe): void
    {
        DB::transaction(function () use ($lease, $safeCode, $retrySafe): void {
            $connection = $this->lockMatching($lease);
            if ($connection === null) {
                return;
            } // Disconnected or reauthorized while provider call was running.
            if ($retrySafe && $connection->token_expires_at?->isFuture()) {
                $connection->forceFill([
                    'last_refresh_result' => $safeCode === 'PROVIDER_NOT_CONFIGURED' ? 'PROVIDER_NOT_CONFIGURED' : 'RATE_LIMIT',
                    'refresh_retry_at' => now()->addMinutes(5),
                    'refresh_lease_token' => null, 'refresh_lease_until' => null,
                ])->save();

                return;
            }
            // Never log provider error descriptions. They occasionally include secrets.
            $this->markReauthorization($connection, in_array($safeCode, [
                'INVALID_GRANT', 'PROVIDER_OUTCOME_UNCERTAIN', 'INVALID_PROVIDER_RESPONSE',
                'RATE_LIMIT', 'REFRESH_UNSUPPORTED',
            ], true) ? $safeCode : 'REFRESH_FAILED');
        }, attempts: 3);
    }

    private function lockMatching(TokenRefreshLease $lease): ?ProviderConnection
    {
        return ProviderConnection::query()->whereKey($lease->connectionId)
            ->where('provider', $lease->provider)
            ->where('status', ProviderConnectionStatus::CONNECTED->value)
            ->where('refresh_lease_token', $lease->leaseToken)->lockForUpdate()->first();
    }

    private function markReauthorization(ProviderConnection $connection, string $safeCode): void
    {
        $connection->forceFill([
            'status' => ProviderConnectionStatus::REAUTH_REQUIRED->value,
            'reconnect_reason' => $safeCode,
            'last_refresh_result' => $safeCode,
            'refresh_lease_token' => null, 'refresh_lease_until' => null,
            'refresh_retry_at' => null,
        ])->save();
        $accounts = SocialAccount::query()->where('organisation_id', $connection->organisation_id)
            ->where('provider_connection_id', $connection->id)
            ->where('status', SocialAccountStatus::ACTIVE->value)->lockForUpdate()->get();
        foreach ($accounts as $account) {
            $account->forceFill(['status' => SocialAccountStatus::RESTRICTED->value])->save();
            SocialAccountCapability::query()->where('organisation_id', $connection->organisation_id)
                ->where('social_account_id', $account->id)->update([
                    'mode' => 'UNAVAILABLE', 'reason' => 'Provider authorization must be renewed', 'checked_at' => now(),
                ]);
            SocialAccountStatusEvent::query()->create([
                'organisation_id' => $connection->organisation_id,
                'brand_id' => $account->brand_id,
                'social_account_id' => $account->id,
                'event_type' => 'PROVIDER_REAUTH_REQUIRED',
                'old_status' => SocialAccountStatus::ACTIVE->value,
                'new_status' => SocialAccountStatus::RESTRICTED->value,
                'recorded_at' => now(),
                'details' => ['reason_code' => $safeCode],
            ]);
        }
    }
}
