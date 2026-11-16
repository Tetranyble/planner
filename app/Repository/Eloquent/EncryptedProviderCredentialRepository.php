<?php

namespace App\Repository\Eloquent;

use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Repository\Contracts\ProviderCredentialRepositoryContract;
use App\Social\Data\OAuthGrant;
use Illuminate\Support\Facades\Crypt;

/** DB-encrypted adapter. Replace behind this interface with managed KMS/Vault in production. */
final class EncryptedProviderCredentialRepository implements ProviderCredentialRepositoryContract
{
    public function store(ProviderConnection $connection, OAuthGrant $grant): bool
    {
        $existing = ProviderConnectionCredential::query()
            ->where('organisation_id', $connection->organisation_id)
            ->where('provider_connection_id', $connection->id)->lockForUpdate()->first();
        // Some providers (Meta, LinkedIn) do not issue OAuth refresh tokens.
        // An access-only grant is valid but must be reauthorized when it expires.
        $cipher = $grant->refreshToken !== null
            ? Crypt::encryptString($grant->refreshToken)
            : $existing?->refresh_token_ciphertext;
        ($existing ?? new ProviderConnectionCredential)->forceFill([
            'organisation_id' => $connection->organisation_id,
            'provider_connection_id' => $connection->id,
            'access_token_ciphertext' => Crypt::encryptString($grant->accessToken),
            'refresh_token_ciphertext' => $cipher,
            'refresh_token_expires_at' => $grant->refreshToken !== null
                ? ($grant->refreshExpiresIn !== null && $grant->refreshExpiresIn > 0
                    ? now()->addSeconds($grant->refreshExpiresIn) : null)
                : $existing?->refresh_token_expires_at,
        ])->save();

        return $cipher !== null;
    }

    public function erase(ProviderConnection $connection): void
    {
        ProviderConnectionCredential::query()
            ->where('organisation_id', $connection->organisation_id)
            ->where('provider_connection_id', $connection->id)->delete();
    }
}
