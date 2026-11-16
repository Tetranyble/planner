<?php

namespace Database\Factories;

use App\Enums\Connection\SocialAccountStatus;
use App\Models\Brand;
use App\Models\ProviderConnection;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialAccount> */
final class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    public function definition(): array
    {
        // Explicit tenant-specific associations are required; no silent cross-tenant fixtures.
        return [
            'external_account_id' => 'fixture-'.str()->uuid(),
            'provider' => 'tiktok', 'account_kind' => 'CREATOR',
            'display_name' => fake()->company(), 'status' => SocialAccountStatus::ARCHIVED->value,
        ];
    }

    public function forBrandAndConnection(Brand $brand, ProviderConnection $connection): static
    {
        if ((int) $brand->organisation_id !== (int) $connection->organisation_id) {
            throw new \InvalidArgumentException('Brand and connection must belong to the same organisation.');
        }

        return $this->state(fn () => [
            'organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id,
            'provider_connection_id' => $connection->id, 'provider' => $connection->provider,
        ]);
    }
}
