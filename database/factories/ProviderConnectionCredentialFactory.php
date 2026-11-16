<?php

namespace Database\Factories;

use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/** @extends Factory<ProviderConnectionCredential> */
final class ProviderConnectionCredentialFactory extends Factory
{
    protected $model = ProviderConnectionCredential::class;

    public function definition(): array
    {
        return ['access_token_ciphertext' => Crypt::encryptString('synthetic-access-token'),
            'refresh_token_ciphertext' => Crypt::encryptString('synthetic-refresh-token')];
    }

    public function forConnection(ProviderConnection $connection): static
    {
        return $this->state(fn () => [
            'organisation_id' => $connection->organisation_id,
            'provider_connection_id' => $connection->id,
        ]);
    }
}
