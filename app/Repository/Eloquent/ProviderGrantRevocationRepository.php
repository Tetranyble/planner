<?php

namespace App\Repository\Eloquent;

use App\Enums\Connection\ProviderConnectionStatus;
use App\Enums\Connection\SocialAccountStatus;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\ProviderGrantGroup;
use App\Models\ProviderGrantLink;
use App\Models\ProviderGrantRevocationRequest;
use App\Models\SocialAccount;
use App\Models\SocialAccountCapability;
use App\Models\SocialAccountStatusEvent;
use App\Repository\Contracts\ProviderGrantRevocationRepositoryContract;
use App\Social\Data\RevocationLease;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/** Canonical durable workflow; credentials never appear in HTTP responses or job payloads. */
final class ProviderGrantRevocationRepository implements ProviderGrantRevocationRepositoryContract
{
    private const BUSY = ['READY', 'IN_FLIGHT', 'UNCERTAIN'];

    private function impact(int $groupId): Collection
    {
        return ProviderGrantLink::query()->where('grant_group_id', $groupId)
            ->whereNull('detached_at')->orderBy('provider_connection_id')
            ->get(['id', 'organisation_id', 'provider_connection_id']);
    }

    private function digest($links): string
    {
        return hash('sha256', $links->map(fn ($link) => $link->organisation_id.':'.$link->provider_connection_id)
            ->implode('|'));
    }

    /** Fail closed for legacy OAuth connections that have not been linked to a grant group. */
    private function legacyConnectionsMissing(ProviderConnection $connection, $links): bool
    {
        $providers = in_array($connection->provider, ['facebook', 'instagram'], true)
            ? ['facebook', 'instagram'] : [$connection->provider];

        return ProviderConnection::query()->whereIn('provider', $providers)
            ->where('external_actor_id', $connection->external_actor_id)
            ->whereIn('status', ['CONNECTED', 'REAUTH_REQUIRED', 'RESTRICTED'])
            ->whereNotIn('id', $links->pluck('provider_connection_id')->all())->exists();
    }

    private function checkImpact(Organisation $org, ProviderGrantGroup $group, $links): void
    {
        if ($links->isEmpty()) {
            throw new ConflictHttpException('Grant has no attached connections.');
        }
        if ($links->contains(fn ($link) => (int) $link->organisation_id !== (int) $org->id)) {
            // Never disclose names or IDs of other organisations.
            throw new ConflictHttpException('This grant may be shared outside your organisation. Remote revocation is blocked.');
        }
        if (ProviderGrantRevocationRequest::query()->where('grant_group_id', $group->id)
            ->whereIn('status', self::BUSY)->exists()) {
            throw new ConflictHttpException('Grant revocation is already in progress or requires reconciliation.');
        }
    }

    public function prepare(Organisation $org, Brand $brand, string $accountUuid, int $actorId, string $reason): ProviderGrantRevocationRequest
    {
        return DB::transaction(function () use ($org, $brand, $accountUuid, $actorId, $reason): ProviderGrantRevocationRequest {
            $account = SocialAccount::query()->where('organisation_id', $org->id)
                ->where('brand_id', $brand->id)->where('uuid', $accountUuid)->firstOrFail();
            if ($account->status !== SocialAccountStatus::ACTIVE->value) {
                throw new ConflictHttpException('Only active accounts may initiate grant revocation.');
            }
            $connection = ProviderConnection::query()->where('organisation_id', $org->id)
                ->whereKey($account->provider_connection_id)->firstOrFail();
            if ($connection->status !== ProviderConnectionStatus::CONNECTED->value) {
                throw new ConflictHttpException('This connection is not currently active.');
            }
            $link = ProviderGrantLink::query()->where('organisation_id', $org->id)
                ->where('provider_connection_id', $connection->id)->whereNull('detached_at')->first();
            if (! $link) {
                throw new ConflictHttpException('Grant ownership must be verified before remote revocation.');
            }
            $group = ProviderGrantGroup::query()->whereKey($link->grant_group_id)->lockForUpdate()->firstOrFail();
            $links = $this->impact($group->id);
            $this->checkImpact($org, $group, $links);
            if ($this->legacyConnectionsMissing($connection, $links)) {
                throw new ConflictHttpException('Legacy connection ownership is incomplete; reconcile the grant graph before remote revocation.');
            }
            if ($group->provider_family !== ($connection->provider === 'instagram' || $connection->provider === 'facebook'
                ? 'meta' : $connection->provider)) {
                throw new ConflictHttpException('Grant identity does not match the provider.');
            }

            return ProviderGrantRevocationRequest::query()->create([
                'organisation_id' => $org->id, 'grant_group_id' => $group->id,
                'provider_connection_id' => $connection->id, 'requested_by_user_id' => $actorId,
                'provider' => $connection->provider, 'status' => 'PREPARED',
                'affected_connections' => $links->count(), 'impact_digest' => $this->digest($links),
                'reason' => $reason, 'expires_at' => now()->addMinutes(15),
            ]);
        }, attempts: 3);
    }

    public function confirm(Organisation $org, string $uuid, int $actorId): ProviderGrantRevocationRequest
    {
        return DB::transaction(function () use ($org, $uuid, $actorId): ProviderGrantRevocationRequest {
            $request = ProviderGrantRevocationRequest::query()->where('organisation_id', $org->id)
                ->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'PREPARED' || $request->expires_at->isPast()) {
                throw new ConflictHttpException('Revocation preview has expired or has already been handled.');
            }
            // A second owner is permitted to confirm; server-side Kinship authorization is mandatory.
            $group = ProviderGrantGroup::query()->whereKey($request->grant_group_id)->lockForUpdate()->firstOrFail();
            $links = $this->impact($group->id);
            $this->checkImpact($org, $group, $links);
            $origin = ProviderConnection::query()->where('organisation_id', $org->id)
                ->whereKey($request->provider_connection_id)->firstOrFail();
            if ($this->legacyConnectionsMissing($origin, $links)
                || $this->digest($links) !== $request->impact_digest || $links->count() !== (int) $request->affected_connections) {
                throw new ConflictHttpException('Grant ownership changed; request a new impact preview.');
            }
            $request->forceFill([
                'status' => $request->provider === 'tiktok' ? 'READY' : 'MANUAL_REQUIRED',
                'confirmed_by_user_id' => $actorId, 'confirmed_at' => now(),
                'safe_error_code' => $request->provider === 'tiktok' ? null : 'NO_VERIFIED_REMOTE_ADAPTER',
            ])->save();

            return $request;
        }, attempts: 3);
    }

    public function directory(Organisation $org): array
    {
        return ProviderGrantRevocationRequest::query()->where('organisation_id', $org->id)
            ->orderByDesc('id')->limit(50)
            ->get(['uuid', 'provider', 'status', 'affected_connections', 'reason', 'expires_at',
                'confirmed_at', 'provider_acknowledged_at', 'safe_error_code', 'created_at'])
            ->map(fn ($row): array => [
                'uuid' => $row->uuid, 'provider' => $row->provider, 'status' => $row->status,
                'affected_connections' => (int) $row->affected_connections, 'reason' => $row->reason,
                'expires_at' => $row->expires_at?->toIso8601String(),
                'confirmed_at' => $row->confirmed_at?->toIso8601String(),
                'acknowledged_at' => $row->provider_acknowledged_at?->toIso8601String(),
                'safe_error_code' => $row->safe_error_code,
            ])->all();
    }

    public function dueIds(int $limit): array
    {
        return ProviderGrantRevocationRequest::query()->where('provider', 'tiktok')
            ->where(fn ($q) => $q->where('status', 'READY')
                ->orWhere(fn ($stale) => $stale->where('status', 'IN_FLIGHT')
                    ->where('lease_until', '<', now())))
            ->orderBy('id')->limit(max(1, min($limit, 100)))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function acquire(int $requestId): ?RevocationLease
    {
        return DB::transaction(function () use ($requestId): ?RevocationLease {
            $request = ProviderGrantRevocationRequest::query()->whereKey($requestId)->lockForUpdate()->first();
            if (! $request) {
                return null;
            }
            if ($request->status === 'IN_FLIGHT' && $request->lease_until?->isPast()) {
                // A crashed process may have revoked the grant. No blind replay.
                $this->markUncertain($request, 'LEASE_EXPIRED_UNCERTAIN');

                return null;
            }
            if ($request->status !== 'READY') {
                return null;
            }
            $group = ProviderGrantGroup::query()->whereKey($request->grant_group_id)->lockForUpdate()->firstOrFail();
            $org = Organisation::query()->whereKey($request->organisation_id)->firstOrFail();
            $links = $this->impact($group->id);
            try {
                $this->checkImpactForWorker($request, $org, $links);
            } catch (ConflictHttpException) {
                $request->forceFill(['status' => 'MANUAL_REQUIRED', 'safe_error_code' => 'GRANT_IMPACT_CHANGED'])->save();

                return null;
            }
            // The same grant may have several connections. Never overlap provider calls
            // with an active credential rotation/inspection on ANY linked connection.
            $linkedConnections = ProviderConnection::query()->whereIn('id', $links->pluck('provider_connection_id')->all())
                ->where('organisation_id', $org->id)->lockForUpdate()->get();
            if ($linkedConnections->contains(fn ($c) => ($c->refresh_lease_until && $c->refresh_lease_until->isFuture())
                || ($c->inspection_lease_until && $c->inspection_lease_until->isFuture()))) {
                return null;
            }
            $connection = $linkedConnections->firstWhere('id', $request->provider_connection_id);
            if ($connection && $this->legacyConnectionsMissing($connection, $links)) {
                $request->forceFill(['status' => 'MANUAL_REQUIRED', 'safe_error_code' => 'LEGACY_GRANT_MAPPING_INCOMPLETE'])->save();

                return null;
            }
            if (! $connection || $connection->status !== ProviderConnectionStatus::CONNECTED->value
                || ($connection->refresh_lease_until && $connection->refresh_lease_until->isFuture())) {
                $request->forceFill(['status' => 'MANUAL_REQUIRED', 'safe_error_code' => 'CONNECTION_NOT_READY'])->save();

                return null;
            }
            $credentials = ProviderConnectionCredential::query()->where('organisation_id', $org->id)
                ->where('provider_connection_id', $connection->id)->lockForUpdate()->first();
            if (! $credentials) {
                $request->forceFill(['status' => 'MANUAL_REQUIRED', 'safe_error_code' => 'MISSING_CREDENTIALS'])->save();

                return null;
            }
            try {
                $token = Crypt::decryptString($credentials->access_token_ciphertext);
            } catch (Throwable) {
                $request->forceFill(['status' => 'MANUAL_REQUIRED', 'safe_error_code' => 'CREDENTIAL_UNREADABLE'])->save();

                return null;
            }
            $lease = (string) Str::uuid();
            $request->forceFill(['status' => 'IN_FLIGHT', 'lease_token' => $lease,
                'lease_until' => now()->addMinutes(3), 'attempted_at' => now()])->save();

            return new RevocationLease((int) $request->id, (int) $group->id, $lease, $request->provider, $token);
        }, attempts: 3);
    }

    private function checkImpactForWorker(ProviderGrantRevocationRequest $request, Organisation $org, $links): void
    {
        if ($links->isEmpty() || $links->contains(fn ($link) => (int) $link->organisation_id !== (int) $org->id)
            || $this->digest($links) !== $request->impact_digest
            || $links->count() !== (int) $request->affected_connections) {
            throw new ConflictHttpException('Grant impact changed.');
        }
    }

    private function leased(RevocationLease $lease): ?ProviderGrantRevocationRequest
    {
        return ProviderGrantRevocationRequest::query()->whereKey($lease->requestId)
            ->where('grant_group_id', $lease->groupId)->where('lease_token', $lease->leaseToken)
            ->where('status', 'IN_FLIGHT')->lockForUpdate()->first();
    }

    public function complete(RevocationLease $lease): bool
    {
        return DB::transaction(function () use ($lease): bool {
            $request = $this->leased($lease);
            if (! $request) {
                return false;
            }
            $group = ProviderGrantGroup::query()->whereKey($request->grant_group_id)->lockForUpdate()->firstOrFail();
            $links = $this->impact($group->id);
            // Provider acknowledgement is already received. Even if membership changed while
            // the network call was in-flight, disable ALL linked connections; do not leave tokens active.
            foreach ($links as $link) {
                $connection = ProviderConnection::query()->where('organisation_id', $link->organisation_id)
                    ->whereKey($link->provider_connection_id)->lockForUpdate()->first();
                if (! $connection) {
                    continue;
                }
                ProviderConnectionCredential::query()->where('organisation_id', $connection->organisation_id)
                    ->where('provider_connection_id', $connection->id)->delete();
                $connection->forceFill([
                    'status' => ProviderConnectionStatus::REVOKED->value,
                    'access_token_secret_ref' => 'encrypted-db:erased', 'refresh_token_secret_ref' => null,
                    'refresh_lease_until' => null, 'refresh_lease_token' => null,
                    'inspection_lease_until' => null, 'inspection_lease_token' => null,
                    'reconnect_reason' => 'PROVIDER_GRANT_REVOKED',
                ])->save();
                $accounts = SocialAccount::query()->where('organisation_id', $connection->organisation_id)
                    ->where('provider_connection_id', $connection->id)
                    ->where('status', SocialAccountStatus::ACTIVE->value)->get();
                foreach ($accounts as $account) {
                    $account->forceFill(['status' => SocialAccountStatus::RESTRICTED->value])->save();
                    SocialAccountStatusEvent::query()->create([
                        'organisation_id' => $account->organisation_id, 'brand_id' => $account->brand_id,
                        'social_account_id' => $account->id, 'event_type' => 'REMOTE_GRANT_REVOKED',
                        'old_status' => SocialAccountStatus::ACTIVE->value,
                        'new_status' => SocialAccountStatus::RESTRICTED->value, 'recorded_at' => now(),
                    ]);
                }
                SocialAccountCapability::query()->where('organisation_id', $connection->organisation_id)
                    ->whereIn('social_account_id', $accounts->pluck('id')->all())
                    ->update(['mode' => 'UNAVAILABLE', 'reason' => 'Provider grant remotely revoked', 'checked_at' => now()]);
                $link->forceFill(['detached_at' => now()])->save();
            }
            $request->forceFill(['status' => 'ACKNOWLEDGED', 'provider_acknowledged_at' => now(),
                'lease_token' => null, 'lease_until' => null, 'safe_error_code' => null])->save();

            return true;
        }, attempts: 3);
    }

    public function uncertain(RevocationLease $lease): void
    {
        DB::transaction(function () use ($lease): void {
            $request = $this->leased($lease);
            if (! $request) {
                return;
            }
            $this->markUncertain($request, 'REMOTE_RESULT_UNCERTAIN');
        }, attempts: 3);
    }

    private function markUncertain(ProviderGrantRevocationRequest $request, string $code): void
    {
        ProviderGrantGroup::query()->whereKey($request->grant_group_id)->lockForUpdate()->firstOrFail();
        $links = $this->impact($request->grant_group_id);
        foreach ($links as $link) {
            $conn = ProviderConnection::query()->where('organisation_id', $link->organisation_id)
                ->whereKey($link->provider_connection_id)->lockForUpdate()->first();
            if ($conn && $conn->status === ProviderConnectionStatus::CONNECTED->value) {
                $conn->forceFill(['status' => ProviderConnectionStatus::RESTRICTED->value,
                    'reconnect_reason' => 'REMOTE_REVOCATION_OUTCOME_UNCERTAIN'])->save();
                SocialAccountCapability::query()->where('organisation_id', $conn->organisation_id)
                    ->whereIn('social_account_id', SocialAccount::query()->where('organisation_id', $conn->organisation_id)
                        ->where('provider_connection_id', $conn->id)->select('id'))
                    ->update(['mode' => 'UNAVAILABLE', 'reason' => 'Grant revocation requires reconciliation', 'checked_at' => now()]);
            }
        }
        $request->forceFill(['status' => 'UNCERTAIN', 'safe_error_code' => $code,
            'lease_token' => null, 'lease_until' => null])->save();
    }
}
