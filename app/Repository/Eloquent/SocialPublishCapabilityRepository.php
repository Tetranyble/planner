<?php

namespace App\Repository\Eloquent;

use App\Domain\Publishing\LiveContracts\PublishingScopePolicy;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\SocialAccount;
use App\Models\SocialAccountCapability;
use App\Repository\Contracts\SocialPublishCapabilityRepositoryContract;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Owns capability persistence; provider HTTP happens in the caller, OUTSIDE transaction. */
final class SocialPublishCapabilityRepository implements SocialPublishCapabilityRepositoryContract
{
    public function context(Organisation $organisation, Brand $brand, string $accountUuid): array
    {
        if ((int) $brand->organisation_id !== (int) $organisation->id) {
            throw new ConflictHttpException('Invalid social account scope.');
        }
        $account = SocialAccount::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('uuid', $accountUuid)->where('status', 'ACTIVE')->firstOrFail();
        $connection = $this->connection($organisation, $account);
        $credential = ProviderConnectionCredential::query()->where('organisation_id', $organisation->id)
            ->where('provider_connection_id', $connection->id)->first();
        if (! $credential?->access_token_ciphertext || ! $connection->token_expires_at
            || ! $connection->token_expires_at->greaterThan(now()->addMinutes(15))) {
            throw new ConflictHttpException('Publishing OAuth credential needs reconnect or renewal.');
        }
        try {
            $token = Crypt::decryptString($credential->access_token_ciphertext);
        } catch (\Throwable) {
            throw new ConflictHttpException('OAuth credential cannot be decrypted.');
        }
        if (! is_string($token) || $token === '' || strlen($token) > 8192) {
            throw new ConflictHttpException('OAuth credential invalid.');
        }

        return ['id' => (int) $account->id, 'provider' => (string) $account->provider,
            'account_kind' => (string) $account->account_kind, 'external_account_id' => (string) $account->external_account_id,
            'scopes' => is_array($connection->scopes) ? $connection->scopes : [],
            'token' => $token, 'credential_digest' => hash('sha256', $credential->access_token_ciphertext)];
    }

    public function record(Organisation $organisation, Brand $brand, int $accountId,
        string $credentialDigest, string $mode, string $reason): array
    {
        if (! in_array($mode, ['AUTOMATIC', 'UNAVAILABLE'], true) || ! preg_match('/^[A-Z0-9_]{3,60}$/D', $reason)) {
            throw new \InvalidArgumentException('Invalid capability decision.');
        }

        return DB::transaction(function () use ($organisation, $brand, $accountId, $credentialDigest, $mode, $reason) {
            Brand::query()->where('organisation_id', $organisation->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $account = SocialAccount::query()->where('organisation_id', $organisation->id)
                ->where('brand_id', $brand->id)->whereKey($accountId)->where('status', 'ACTIVE')->lockForUpdate()->firstOrFail();
            $connection = $this->connection($organisation, $account, true);
            $credential = ProviderConnectionCredential::query()->where('organisation_id', $organisation->id)
                ->where('provider_connection_id', $connection->id)->lockForUpdate()->first();
            if (! $credential?->access_token_ciphertext || ! hash_equals($credentialDigest, hash('sha256', $credential->access_token_ciphertext))) {
                throw new ConflictHttpException('OAuth grant changed during verification. Recheck publishing access.');
            }
            if ($mode === 'AUTOMATIC' && ! PublishingScopePolicy::permitted($account->provider, $account->account_kind,
                is_array($connection->scopes) ? $connection->scopes : [])) {
                throw new ConflictHttpException('Provider scopes are no longer sufficient.');
            }
            if ($mode === 'AUTOMATIC' && (! $connection->token_expires_at ||
                ! $connection->token_expires_at->greaterThan(now()->addMinutes(15)))) {
                throw new ConflictHttpException('Grant expired during permission verification.');
            }
            $until = $mode === 'AUTOMATIC' ? now()->addMinutes(30) : null;
            if ($until && $connection->token_expires_at->lt($until)) {
                $until = $connection->token_expires_at->copy()->subMinutes(10);
            }
            SocialAccountCapability::query()->updateOrCreate([
                'organisation_id' => $organisation->id, 'social_account_id' => $account->id, 'capability' => 'post.publish',
            ], ['brand_id' => $brand->id, 'mode' => $mode, 'reason' => $reason,
                'checked_at' => now(), 'valid_until' => $until]);

            return ['account_uuid' => (string) $account->uuid, 'provider' => $account->provider,
                'capability' => 'post.publish', 'mode' => $mode, 'reason_code' => $reason,
                'valid_until' => $until?->toIso8601String()];
        }, 3);
    }

    private function connection(Organisation $organisation, SocialAccount $account, bool $lock = false): ProviderConnection
    {
        $query = ProviderConnection::query()->where('organisation_id', $organisation->id)
            ->whereKey($account->provider_connection_id)->where('provider', $account->provider)
            ->where('status', 'CONNECTED');
        if ($lock) {
            $query->lockForUpdate();
        }
        $connection = $query->first();
        if (! $connection) {
            throw new ConflictHttpException('Provider connection is unavailable.');
        }

        return $connection;
    }
}
