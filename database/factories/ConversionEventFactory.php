<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ConversionEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ConversionEvent>
 */
class ConversionEventFactory extends Factory
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
            'source_system' => 'test',
            'event_type' => 'TEST',
            'occurred_at' => now(),
            'received_at' => now(),
            'properties' => [],
        ];
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
