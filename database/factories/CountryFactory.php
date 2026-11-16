<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->country,
            'iso3' => $this->faker->lexify('???'),
            'iso2' => $this->faker->lexify('??'),
            'numeric_code' => $this->faker->numerify('###'),
            'phone_code' => '+'.$this->faker->numerify('###'),
            'capital' => $this->faker->city,
            'currency' => 'USD',
            'currency_name' => 'Dollar',
            'currency_symbol' => '$',
            'tld' => '.'.$this->faker->lexify('??'),
            'native' => $this->faker->country,
            'region' => $this->faker->randomElement(['Africa', 'Europe', 'Asia']),
            'subregion' => $this->faker->word,
            'latitude' => $this->faker->latitude,
            'longitude' => $this->faker->longitude,
            'emoji' => $this->faker->emoji,
            'emojiU' => strtoupper($this->faker->lexify('U+1F1??')),
            'uuid' => $this->faker->uuid,
        ];
    }
}
