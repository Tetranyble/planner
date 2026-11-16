<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ContentSeries;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentSeries> */
final class ContentSeriesFactory extends Factory
{
    protected $model = ContentSeries::class;

    /** Scoped foreign keys are intentionally required via forBrand() or a more specific factory state. */
    public function definition(): array
    {
        return ['name' => fake()->sentence(3), 'summary' => fake()->sentence(), 'status' => 'ACTIVE'];
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
