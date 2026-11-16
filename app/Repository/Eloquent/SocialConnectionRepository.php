<?php

namespace App\Repository\Eloquent;

use App\Enums\Connection\ProviderConnectionStatus;
use App\Enums\Connection\SocialAccountStatus;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\SocialAccount;
use App\Models\SocialAccountCapability;
use App\Models\SocialAccountStatusEvent;
use App\Models\SocialOauthAttempt;
use App\Models\User;
use App\Repository\Contracts\ProviderCredentialRepositoryContract;
use App\Repository\Contracts\ProviderGrantOwnershipRepositoryContract;
use App\Repository\Contracts\SocialConnectionRepositoryContract;
use App\Social\Data\DiscoveredSocialAccount;
use App\Social\Data\OAuthGrant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

final class SocialConnectionRepository implements SocialConnectionRepositoryContract
{
    public function __construct(private readonly ProviderCredentialRepositoryContract $credentials,
        private readonly ProviderGrantOwnershipRepositoryContract $ownership) {}

    public function createAttempt(Organisation $organisation, Brand $brand, User $actor,
        string $provider, string $digest, string $redirectUri): void
    {
        SocialOauthAttempt::query()->create([
            'organisation_id' => $organisation->id, 'brand_id' => $brand->id,
            'user_id' => $actor->id, 'provider' => $provider,
            'state_digest' => $digest, 'redirect_uri' => $redirectUri,
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    public function consumeAttempt(string $digest, string $provider, User $actor): SocialOauthAttempt
    {
        return DB::transaction(function () use ($digest, $provider, $actor): SocialOauthAttempt {
            $attempt = SocialOauthAttempt::query()->where('state_digest', $digest)
                ->where('provider', $provider)->lockForUpdate()->first();
            // Deliberately give the same error for unknown and wrong-user state.
            if ($attempt === null || (int) $attempt->user_id !== (int) $actor->getKey()) {
                throw new GoneHttpException('OAuth state expired or invalid. Start a new connection.');
            }
            if ($attempt->consumed_at !== null || $attempt->expires_at->isPast()) {
                throw new GoneHttpException('OAuth state expired or already used.');
            }
            $attempt->forceFill(['consumed_at' => now()])->save();

            return $attempt;
        }, attempts: 3);
    }

    public function findAttemptScope(SocialOauthAttempt $attempt): array
    {
        $organisation = Organisation::query()->findOrFail($attempt->organisation_id);
        $brand = Brand::query()->where('organisation_id', $organisation->id)
            ->findOrFail($attempt->brand_id);

        return compact('organisation', 'brand');
    }

    public function directory(Organisation $organisation, Brand $brand): array
    {
        // No credential ciphertext or provider secret refs are returned to UI/API clients.
        $connectionIds = SocialAccount::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->pluck('provider_connection_id')->unique()->values()->all();
        $connections = ProviderConnection::query()->where('organisation_id', $organisation->id)
            ->whereIn('id', $connectionIds)
            ->get(['id', 'status', 'token_expires_at', 'reconnect_reason'])->keyBy('id');

        return SocialAccount::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->with('capabilities')
            ->orderBy('provider')->orderBy('display_name')->get()->map(fn (SocialAccount $row): array => [
                'connection' => ($related = $connections->get($row->provider_connection_id)) ? [
                    'status' => $related->status,
                    'expires_at' => $related->token_expires_at?->toIso8601String(),
                    'reconnect_reason' => $related->status === ProviderConnectionStatus::REAUTH_REQUIRED->value
                        ? $related->reconnect_reason : null,
                ] : null,
                'uuid' => $row->uuid, 'provider' => $row->provider,
                'display_name' => $row->display_name, 'handle' => $row->handle,
                'account_kind' => $row->account_kind, 'status' => $row->status,
                'last_sync_at' => $row->last_sync_at?->toIso8601String(),
                'capabilities' => $row->capabilities->map(fn ($cap) => [
                    'capability' => $cap->capability, 'mode' => $cap->mode, 'reason' => $cap->reason,
                ])->all(),
            ])->all();
    }

    public function connect(Organisation $organisation, Brand $brand, string $provider,
        OAuthGrant $grant, array $discovered, callable $capabilities): array
    {
        if (count($discovered) === 0) {
            throw ValidationException::withMessages(['provider' => 'No usable social account was discovered.']);
        }

        return DB::transaction(function () use ($organisation, $brand, $provider, $grant, $discovered, $capabilities): array {
            // Protect against concurrent OAuth callbacks and member revocations.
            Organisation::query()->whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            Brand::query()->where('organisation_id', $organisation->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $actorId = $grant->actorId !== '' ? $grant->actorId : 'channel:'.$discovered[0]->externalId;
            $connection = ProviderConnection::query()->where('organisation_id', $organisation->id)
                ->where('provider', $provider)->where('external_actor_id', $actorId)
                ->lockForUpdate()->first();
            if ($connection === null) {
                $connection = new ProviderConnection;
                $connection->forceFill([
                    'organisation_id' => $organisation->id, 'provider' => $provider,
                    'external_actor_id' => $actorId,
                    'access_token_secret_ref' => 'encrypted-db:pending',
                ]);
            }
            // A fresh login must not inherit a previously invalid refresh token. Only
            // incremental consent on an already CONNECTED grant may preserve it.
            if ($connection->exists && $connection->status !== ProviderConnectionStatus::CONNECTED->value) {
                $this->credentials->erase($connection);
            }
            $connection->forceFill([
                'status' => ProviderConnectionStatus::CONNECTED->value,
                'scopes' => $grant->scopes,
                'token_expires_at' => $grant->expiresIn > 0 ? now()->addSeconds($grant->expiresIn) : null,
                'last_verified_at' => now(), 'reconnect_reason' => null,
                'refresh_lease_token' => null, 'refresh_lease_until' => null,
                'refresh_retry_at' => null, 'refresh_attempts' => 0, 'last_refresh_result' => null,
            ])->save();
            $hasRefreshToken = $this->credentials->store($connection, $grant);
            $connection->forceFill([
                'access_token_secret_ref' => 'encrypted-db:provider-credentials:'.$connection->uuid,
                'refresh_token_secret_ref' => $hasRefreshToken ? 'encrypted-db:provider-credentials:'.$connection->uuid : null,
            ])->save();
            if ($grant->actorId !== '') {
                $this->ownership->linkGrant($connection, $grant->actorId);
            }
            $results = [];
            foreach ($discovered as $item) {
                if (! $item instanceof DiscoveredSocialAccount) {
                    continue;
                }
                $account = SocialAccount::query()->where('organisation_id', $organisation->id)
                    ->where('provider', $provider)->where('external_account_id', $item->externalId)
                    ->lockForUpdate()->first();
                if ($account !== null && (int) $account->brand_id !== (int) $brand->id) {
                    throw new ConflictHttpException('Account is already assigned to another brand in this organisation.');
                }
                $previous = $account?->status;
                $account ??= new SocialAccount;
                $account->forceFill([
                    'organisation_id' => $organisation->id, 'brand_id' => $brand->id,
                    'provider_connection_id' => $connection->id, 'provider' => $provider,
                    'external_account_id' => $item->externalId, 'account_kind' => $item->kind,
                    'display_name' => $item->displayName, 'handle' => $item->handle,
                    'status' => SocialAccountStatus::ACTIVE->value, 'last_sync_at' => now(),
                ])->save();
                foreach ($capabilities($item) as $name => $capability) {
                    SocialAccountCapability::query()->updateOrCreate([
                        'organisation_id' => $organisation->id, 'social_account_id' => $account->id,
                        'capability' => $name,
                    ], [
                        'brand_id' => $brand->id, 'mode' => $capability['mode'],
                        'reason' => $capability['reason'], 'checked_at' => now(),
                    ]);
                }
                SocialAccountStatusEvent::query()->create([
                    'organisation_id' => $organisation->id, 'brand_id' => $brand->id,
                    'social_account_id' => $account->id,
                    'event_type' => $previous === null ? 'CONNECTED' : 'RECONNECTED',
                    'old_status' => $previous, 'new_status' => SocialAccountStatus::ACTIVE->value,
                    'recorded_at' => now(),
                ]);
                $results[] = $account->uuid;
            }

            return $results;
        }, attempts: 3);
    }

    public function lockScope(Organisation $organisation, Brand $brand): void
    {
        Organisation::query()->whereKey($organisation->id)->lockForUpdate()->firstOrFail();
        Brand::query()->where('organisation_id', $organisation->id)
            ->whereKey($brand->id)->lockForUpdate()->firstOrFail();
    }

    public function disconnect(Organisation $organisation, Brand $brand, string $accountUuid): SocialAccount
    {
        return DB::transaction(function () use ($organisation, $brand, $accountUuid): SocialAccount {
            Organisation::query()->whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            Brand::query()->where('organisation_id', $organisation->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $account = SocialAccount::query()->where('organisation_id', $organisation->id)
                ->where('brand_id', $brand->id)->where('uuid', $accountUuid)->lockForUpdate()->firstOrFail();
            if ($account->status === SocialAccountStatus::ARCHIVED->value) {
                throw new ConflictHttpException('Account has already been disconnected.');
            }
            $previous = $account->status;
            $account->forceFill(['status' => SocialAccountStatus::ARCHIVED->value])->save();
            SocialAccountCapability::query()->where('organisation_id', $organisation->id)
                ->where('brand_id', $brand->id)->where('social_account_id', $account->id)
                ->update(['mode' => 'UNAVAILABLE', 'reason' => 'Account disconnected locally', 'checked_at' => now()]);
            SocialAccountStatusEvent::query()->create([
                'organisation_id' => $organisation->id, 'brand_id' => $brand->id,
                'social_account_id' => $account->id, 'event_type' => 'DISCONNECTED_LOCALLY',
                'old_status' => $previous, 'new_status' => SocialAccountStatus::ARCHIVED->value,
                'recorded_at' => now(),
            ]);
            $active = SocialAccount::query()->where('organisation_id', $organisation->id)
                ->where('provider_connection_id', $account->provider_connection_id)
                ->where('status', SocialAccountStatus::ACTIVE->value)->exists();
            if (! $active) {
                $connection = ProviderConnection::query()->where('organisation_id', $organisation->id)
                    ->whereKey($account->provider_connection_id)->lockForUpdate()->firstOrFail();
                $this->credentials->erase($connection);
                $this->ownership->markDetached($connection);
                $connection->forceFill([
                    'status' => ProviderConnectionStatus::DISCONNECTED->value,
                    'access_token_secret_ref' => 'encrypted-db:erased',
                    'refresh_token_secret_ref' => null,
                ])->save();
            }

            return $account;
        }, attempts: 3);
    }
}
