<?php

namespace App\Repository\Eloquent;

use App\Enums\Connection\ProviderConnectionStatus;
use App\Enums\Connection\SocialAccountStatus;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\SocialAccount;
use App\Models\SocialAccountCapability;
use App\Models\SocialAccountStatusEvent;
use App\Repository\Contracts\MetaCapabilityInspectionRepositoryContract;
use App\Social\Data\MetaInspectionLease;
use App\Social\Data\MetaTokenInspection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/** Tenant-aware persistence of Meta inspection. Provider HTTP always outside transactions. */
final class MetaCapabilityInspectionRepository implements MetaCapabilityInspectionRepositoryContract
{
    public function dueIds(int $limit, int $minutes): array
    {
        return ProviderConnection::query()->whereIn('provider', ['facebook', 'instagram'])
            ->where('status', ProviderConnectionStatus::CONNECTED->value)
            ->where(fn ($q) => $q->whereNull('last_verified_at')
                ->orWhere('last_verified_at', '<=', now()->subMinutes($minutes))
                ->orWhere('data_access_expires_at', '<=', now()->addMinutes(10)))
            ->where(fn ($q) => $q->whereNull('inspection_retry_at')->orWhere('inspection_retry_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('inspection_lease_until')->orWhere('inspection_lease_until', '<=', now()))
            ->orderBy('id')->limit(max(1, min($limit, 100)))->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function acquire(int $connectionId, int $minutes): ?MetaInspectionLease
    {
        return DB::transaction(function () use ($connectionId, $minutes): ?MetaInspectionLease {
            $row = ProviderConnection::query()->whereKey($connectionId)->lockForUpdate()->first();
            if (! $row || ! in_array($row->provider, ['facebook', 'instagram'], true)
                || $row->status !== ProviderConnectionStatus::CONNECTED->value
                || (($row->last_verified_at && $row->last_verified_at->gt(now()->subMinutes($minutes)))
                    && (! $row->data_access_expires_at || $row->data_access_expires_at->gt(now()->addMinutes(10))))
                || ($row->inspection_retry_at && $row->inspection_retry_at->isFuture())
                || ($row->inspection_lease_until && $row->inspection_lease_until->isFuture())
                || ($row->refresh_lease_until && $row->refresh_lease_until->isFuture())) {
                return null;
            }
            $credential = ProviderConnectionCredential::query()->where('organisation_id', $row->organisation_id)
                ->where('provider_connection_id', $row->id)->lockForUpdate()->first();
            if (! $credential || ! $credential->access_token_ciphertext) {
                $this->restrict($row, 'MISSING_CREDENTIAL');

                return null;
            }
            try {
                $token = Crypt::decryptString($credential->access_token_ciphertext);
            } catch (Throwable) {
                $this->restrict($row, 'CREDENTIAL_UNREADABLE');

                return null;
            }
            $lease = (string) Str::uuid();
            $row->forceFill(['inspection_lease_token' => $lease,
                'inspection_lease_until' => now()->addSeconds(90),
                'inspection_attempts' => (int) $row->inspection_attempts + 1,
                'last_inspection_result' => 'CHECKING'])->save();

            return new MetaInspectionLease((int) $row->id, $lease, $token,
                hash('sha256', $token), (string) $row->external_actor_id);
        }, attempts: 3);
    }

    public function complete(MetaInspectionLease $lease, MetaTokenInspection $inspection): bool
    {
        return DB::transaction(function () use ($lease, $inspection): bool {
            $row = $this->locked($lease);
            if (! $row) {
                return false;
            }
            $credential = ProviderConnectionCredential::query()->where('organisation_id', $row->organisation_id)
                ->where('provider_connection_id', $row->id)->lockForUpdate()->first();
            try {
                $current = $credential ? Crypt::decryptString($credential->access_token_ciphertext) : '';
            } catch (Throwable) {
                $current = '';
            }
            if (! hash_equals($lease->tokenDigest, hash('sha256', $current))) {
                // Another writer replaced the token while our read-only API call was in flight.
                $this->clearLease($row, 'TOKEN_CHANGED');

                return false;
            }
            $appId = (string) config('social_integrations.providers.facebook.client_id');
            if (! $inspection->valid || $inspection->appId !== $appId
                || $inspection->userId === '' || $inspection->userId !== $lease->expectedActorId
                || ($inspection->expiresAt !== null && $inspection->expiresAt <= time())
                || ($inspection->dataAccessExpiresAt !== null && $inspection->dataAccessExpiresAt <= time())) {
                $this->restrict($row, 'META_GRANT_INVALID');

                return false;
            }
            $row->forceFill([
                'scopes' => $inspection->scopes,
                'last_verified_at' => now(),
                'token_expires_at' => $inspection->expiresAt ? now()->setTimestamp($inspection->expiresAt) : null,
                'data_access_expires_at' => $inspection->dataAccessExpiresAt ? now()->setTimestamp($inspection->dataAccessExpiresAt) : null,
                'inspection_attempts' => 0, 'inspection_retry_at' => null,
            ])->save();
            $accounts = SocialAccount::query()->where('organisation_id', $row->organisation_id)
                ->where('provider_connection_id', $row->id)->lockForUpdate()->get();
            foreach ($accounts as $account) {
                if ($account->status !== SocialAccountStatus::ACTIVE->value) {
                    continue;
                }
                $required = $account->provider === 'instagram'
                    ? ['pages_show_list', 'pages_read_engagement', 'instagram_basic']
                    : ['pages_show_list', 'pages_read_engagement'];
                $valid = true;
                foreach ($required as $scope) {
                    if (! $inspection->allows($scope, (string) $account->external_account_id)) {
                        $valid = false;
                    }
                }
                $this->capability($account, 'profile.read', $valid ? 'READ_ONLY' : 'UNAVAILABLE',
                    $valid ? 'META_PERMISSION_VERIFIED' : 'META_SCOPE_OR_TARGET_MISSING');
                $this->capability($account, $account->provider === 'instagram' ? 'media.publish' : 'post.publish',
                    'UNAVAILABLE', 'PUBLISHING_ADAPTER_NOT_APPROVED');
            }
            $this->clearLease($row, 'VERIFIED');

            return true;
        }, attempts: 3);
    }

    public function fail(MetaInspectionLease $lease): void
    {
        DB::transaction(function () use ($lease): void {
            $row = $this->locked($lease);
            if (! $row) {
                return;
            }
            $row->forceFill(['inspection_retry_at' => now()->addMinutes(15)])->save();
            $this->disableCapabilities($row, 'META_INSPECTION_UNAVAILABLE');
            $this->clearLease($row, 'RETRY_PENDING');
        }, attempts: 3);
    }

    private function locked(MetaInspectionLease $lease): ?ProviderConnection
    {
        return ProviderConnection::query()->whereKey($lease->connectionId)
            ->where('status', ProviderConnectionStatus::CONNECTED->value)
            ->where('inspection_lease_token', $lease->leaseToken)->lockForUpdate()->first();
    }

    private function capability(SocialAccount $account, string $name, string $mode, string $reason): void
    {
        SocialAccountCapability::query()->updateOrCreate([
            'organisation_id' => $account->organisation_id, 'social_account_id' => $account->id, 'capability' => $name,
        ], ['brand_id' => $account->brand_id, 'mode' => $mode, 'reason' => $reason,
            'checked_at' => now(), 'valid_until' => now()->addMinutes(75)]);
    }

    private function disableCapabilities(ProviderConnection $row, string $reason): void
    {
        $ids = SocialAccount::query()->where('organisation_id', $row->organisation_id)
            ->where('provider_connection_id', $row->id)->pluck('id');
        SocialAccountCapability::query()->where('organisation_id', $row->organisation_id)
            ->whereIn('social_account_id', $ids)->update([
                'mode' => 'UNAVAILABLE', 'reason' => $reason, 'checked_at' => now(), 'valid_until' => null]);
    }

    private function restrict(ProviderConnection $row, string $reason): void
    {
        $row->forceFill(['status' => ProviderConnectionStatus::REAUTH_REQUIRED->value,
            'reconnect_reason' => $reason, 'inspection_retry_at' => null])->save();
        $accounts = SocialAccount::query()->where('organisation_id', $row->organisation_id)
            ->where('provider_connection_id', $row->id)
            ->where('status', SocialAccountStatus::ACTIVE->value)->lockForUpdate()->get();
        foreach ($accounts as $account) {
            $account->forceFill(['status' => SocialAccountStatus::RESTRICTED->value])->save();
            SocialAccountStatusEvent::query()->create([
                'organisation_id' => $row->organisation_id, 'brand_id' => $account->brand_id,
                'social_account_id' => $account->id, 'event_type' => 'META_REAUTH_REQUIRED',
                'old_status' => SocialAccountStatus::ACTIVE->value,
                'new_status' => SocialAccountStatus::RESTRICTED->value,
                'recorded_at' => now(), 'details' => ['reason_code' => $reason],
            ]);
        }
        $this->disableCapabilities($row, $reason);
        $this->clearLease($row, $reason);
    }

    private function clearLease(ProviderConnection $row, string $result): void
    {
        $row->forceFill(['inspection_lease_token' => null, 'inspection_lease_until' => null,
            'last_inspection_result' => $result])->save();
    }
}
