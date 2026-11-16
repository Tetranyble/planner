<?php

namespace App\Repository\Eloquent;

use App\Domain\Content\ProviderPublishCapabilityMatrix;
use App\Domain\Publishing\Media\MediaReleaseRequirements;
use App\Enums\Connection\ProviderConnectionStatus;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\SocialAccount;
use App\Models\SocialAccountCapability;
use App\Repository\Contracts\PublicationReadinessRepositoryContract;
use App\Services\Publishing\ProcessMediaPublicationRuns;

final class PublicationReadinessRepository implements PublicationReadinessRepositoryContract
{
    public function hasVerifiedAutomaticPublishCapability(Organisation $organisation, Brand $brand, SocialAccount $account): bool
    {
        $capability = ProviderPublishCapabilityMatrix::capabilityFor((string) $account->provider);
        if ($capability === null || (int) $account->organisation_id !== (int) $organisation->id
            || (int) $account->brand_id !== (int) $brand->id) {
            return false;
        }

        // Exact provider-specific entitlement, never generic posting rights.
        return SocialAccountCapability::query()
            ->where('organisation_id', $organisation->id)->where('brand_id', $brand->id)
            ->where('social_account_id', $account->id)->where('capability', $capability)
            ->where('mode', 'AUTOMATIC')->whereNotNull('valid_until')
            ->where('valid_until', '>', now())->where('checked_at', '<=', now())->exists();
    }

    /** Requires an actual recently inspected grant, approved media product and a released adapter. */
    public function hasVerifiedMediaPublishGrant(Organisation $organisation, Brand $brand, SocialAccount $account): bool
    {
        if ((int) $account->organisation_id !== (int) $organisation->id || (int) $account->brand_id !== (int) $brand->id
            || ! in_array($account->provider, ['instagram', 'tiktok'], true)
            || ! ProcessMediaPublicationRuns::enabled()
            || ! (bool) config('planner.media_entitlements.'.$account->provider, false)
            || ! (bool) config('planner.media_delivery_origin_verified', false)
            || trim((string) config('planner.media_distribution_hosts', '')) === '') {
            return false;
        }
        $connection = ProviderConnection::query()->where('organisation_id', $organisation->id)
            ->whereKey($account->provider_connection_id)->where('status', 'CONNECTED')->first();
        if (! $connection || $connection->provider !== $account->provider || ! $connection->last_verified_at
            || $connection->last_verified_at->lessThan(now()->subMinutes(30))
            || ! $connection->token_expires_at || $connection->token_expires_at->lessThanOrEqualTo(now()->addMinutes(15))) {
            return false;
        }
        $scopes = is_array($connection->scopes) ? $connection->scopes : [];
        foreach (MediaReleaseRequirements::forProvider($account->provider) as $scope) {
            if (! in_array($scope, $scopes, true)) {
                return false;
            }
        }

        return ProviderConnectionCredential::query()->where('organisation_id', $organisation->id)
            ->where('provider_connection_id', $connection->id)->whereNotNull('access_token_ciphertext')->exists();
    }

    public function connectionIsHealthy(Organisation $organisation, Brand $brand, SocialAccount $account): array
    {
        if ((int) $account->organisation_id !== (int) $organisation->id || (int) $account->brand_id !== (int) $brand->id) {
            return ['connection_active' => false, 'credential_valid' => false];
        }
        $connection = ProviderConnection::query()->where('organisation_id', $organisation->id)
            ->whereKey($account->provider_connection_id)->first();
        if (! $connection || $connection->status !== ProviderConnectionStatus::CONNECTED->value || $connection->provider !== $account->provider) {
            return ['connection_active' => false, 'credential_valid' => false];
        }
        // An expiring token must have room for a future scheduling/dispatch boundary.
        // No secret is decrypted or sent to the client.
        $expiry = $connection->token_expires_at;
        $hasLifetime = $expiry !== null && $expiry->greaterThan(now()->addMinutes(15));
        $credential = ProviderConnectionCredential::query()
            ->where('organisation_id', $organisation->id)->where('provider_connection_id', $connection->id)->first();

        return ['connection_active' => true,
            'credential_valid' => $hasLifetime && $credential !== null
                && ! empty($credential->access_token_ciphertext)];
    }
}
