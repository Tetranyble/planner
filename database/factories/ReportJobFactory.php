<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ReportJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportJob>
 */
class ReportJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
        ];
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
