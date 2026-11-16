<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ContentLabel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentLabel> */
final class ContentLabelFactory extends Factory
{
    protected $model = ContentLabel::class;

    /** Scoped foreign keys are intentionally required via forBrand() or a more specific factory state. */
    public function definition(): array
    {
        return ['label' => fake()->unique()->word(), 'color_hex' => '#6948D7'];
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
