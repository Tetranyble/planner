<?php

namespace Database\Factories;

use App\Models\AiToolAttempt;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiToolAttempt>
 */
class AiToolAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_number' => 1,
            'started_at' => now(),
        ];
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
