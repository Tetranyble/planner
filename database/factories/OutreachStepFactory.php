<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\OutreachStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutreachStep>
 */
class OutreachStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'step_number' => 1,
            'delay_minutes' => 1,
            'body_template' => 'Fixture text for an approved internal test',
        ];
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
