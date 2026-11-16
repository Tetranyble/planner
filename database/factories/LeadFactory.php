<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Country;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_name' => $this->faker->company(),
        ];
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }

    public function forBrandAndCountry(Brand $brand, Country $country): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id,
            'brand_id' => $brand->id, 'country_id' => $country->id]);
    }
}
