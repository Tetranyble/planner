<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->city(),
            'state_id' => State::factory(),
            'state_code' => strtoupper($this->faker->lexify('???')),
            'state_name' => $this->faker->word().' State',
            'country_id' => Country::factory(),
            'country_code' => strtoupper($this->faker->lexify('??')),
            'country_name' => $this->faker->country(),
            'latitude' => $this->faker->latitude(-90, 90),
            'longitude' => $this->faker->longitude(-180, 180),
            'wikiDataId' => 'Q'.$this->faker->numberBetween(1, 9999999),
        ];
    }

    public function forState(State $state): static
    {
        return $this->state(fn () => [
            'state_id' => $state->id,
            'state_code' => $state->state_code,
            'state_name' => $state->name,
            'country_id' => $state->country_id,
            'country_code' => $state->country_code,
            'country_name' => $state->country_name,
        ]);
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
