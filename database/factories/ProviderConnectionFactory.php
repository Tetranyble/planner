<?php

namespace Database\Factories;

use App\Enums\Connection\ProviderConnectionStatus;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProviderConnection> */
final class ProviderConnectionFactory extends Factory
{
    protected $model = ProviderConnection::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'provider' => 'tiktok', 'external_actor_id' => 'fixture-'.str()->uuid(),
            'access_token_secret_ref' => 'fixture:no-secret',
            'refresh_token_secret_ref' => null, 'scopes' => [],
            'token_expires_at' => null,
            'status' => ProviderConnectionStatus::DISCONNECTED->value,
            'refresh_lease_token' => null, 'refresh_lease_until' => null,
            'refresh_retry_at' => null, 'refresh_attempts' => 0,
        ];
    }

    public function forOrganisation(Organisation $org): static
    {
        return $this->state(fn () => ['organisation_id' => $org->id]);
    }

    public function forProvider(string $provider): static
    {
        return $this->state(fn () => ['provider' => $provider]);
    }
}
