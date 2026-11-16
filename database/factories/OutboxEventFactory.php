<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OutboxEvent>
 */
class OutboxEventFactory extends Factory
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
            'aggregate_type' => 'TEST',
            'event_type' => 'TEST',
            'event_key' => 'fixture-'.Str::random(16),
            'payload' => [],
        ];
    }

    public function forOrganisation(Organisation $organisation): static
    {
        return $this->state(fn (): array => ['organisation_id' => $organisation->id]);
    }
}
