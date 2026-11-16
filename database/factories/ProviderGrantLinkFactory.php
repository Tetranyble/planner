<?php

namespace Database\Factories;

use App\Models\ProviderConnection;
use App\Models\ProviderGrantGroup;
use App\Models\ProviderGrantLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProviderGrantLink> */
final class ProviderGrantLinkFactory extends Factory
{
    protected $model = ProviderGrantLink::class;

    public function definition(): array
    {
        // Tenant-scoped relation is supplied explicitly, not guessed.
        return ['grant_group_id' => ProviderGrantGroup::factory()];
    }

    public function forConnection(ProviderConnection $connection): static
    {
        return $this->state(fn () => [
            'organisation_id' => $connection->organisation_id,
            'provider_connection_id' => $connection->id,
        ]);
    }
}
