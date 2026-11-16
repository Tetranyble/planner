<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\PublishingWindow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublishingWindow>
 */
class PublishingWindowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'local_start' => '10:00:00',
            'local_end' => '11:00:00',
            'timezone' => 'Africa/Lagos',
        ];
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
