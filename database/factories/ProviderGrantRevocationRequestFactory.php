<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderGrantGroup;
use App\Models\ProviderGrantRevocationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProviderGrantRevocationRequest> */
final class ProviderGrantRevocationRequestFactory extends Factory
{
    protected $model = ProviderGrantRevocationRequest::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'grant_group_id' => ProviderGrantGroup::factory(),
            'provider_connection_id' => null, // explicit fixture relation is mandatory
            'requested_by_user_id' => User::factory(),
            'provider' => 'tiktok', 'status' => 'PREPARED',
            'affected_connections' => 1, 'impact_digest' => hash('sha256', fake()->uuid()),
            'reason' => 'Testing explicit provider revocation',
            'expires_at' => now()->addMinutes(15),
        ];
    }

    public function forConnection(ProviderConnection $connection, ProviderGrantGroup $group, User $actor): static
    {
        return $this->state(fn () => [
            'organisation_id' => $connection->organisation_id, 'provider_connection_id' => $connection->id,
            'grant_group_id' => $group->id, 'provider' => $connection->provider,
            'requested_by_user_id' => $actor->id,
        ]);
    }
}
