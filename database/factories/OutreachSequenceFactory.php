<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\OutreachSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutreachSequence>
 */
class OutreachSequenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
        ];
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
