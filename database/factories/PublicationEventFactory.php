<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\PublicationEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PublicationEvent>
 */
class PublicationEventFactory extends Factory
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
            'to_state' => 'test',
            'event_type' => 'TEST',
            'actor_type' => 'TEST',
            'occurred_at' => now(),
            'details' => [],
        ];
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
