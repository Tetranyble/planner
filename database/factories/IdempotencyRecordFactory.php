<?php

namespace Database\Factories;

use App\Models\IdempotencyRecord;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IdempotencyRecord>
 */
class IdempotencyRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'client_key' => 'fixture-'.Str::random(16),
            'operation' => 'TEST',
            'idempotency_key' => 'fixture-'.Str::random(16),
            'request_hash' => str_repeat('a', 64),
            'expires_at' => now(),
        ];
    }

    public function forOrganisation(Organisation $organisation): static
    {
        return $this->state(fn (): array => ['organisation_id' => $organisation->id]);
    }
}
