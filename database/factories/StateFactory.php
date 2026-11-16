<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<State>
 */
class StateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'country_id' => Country::factory(),
            'country_code' => strtoupper($this->faker->lexify('??')),
            'country_name' => $this->faker->country(),
            'state_code' => strtoupper($this->faker->lexify('???')),
            'type' => $this->faker->randomElement(['state', 'province', 'region']),
            'latitude' => $this->faker->latitude(-90, 90),
            'longitude' => $this->faker->longitude(-180, 180),
        ];
    }

    public function forCountry(Country $country): static
    {
        return $this->state(fn () => [
            'country_id' => $country->id,
            'country_code' => $country->iso2,
            'country_name' => $country->name,
        ]);
    }
}
