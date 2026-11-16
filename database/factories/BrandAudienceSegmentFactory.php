<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandAudienceSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandAudienceSegment> */
final class BrandAudienceSegmentFactory extends Factory
{
    protected $model = BrandAudienceSegment::class;

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'organisation_id' => fn (array $attributes) => Brand::query()->findOrFail($attributes['brand_id'])->organisation_id,
            'name' => 'School administrators '.$this->faker->unique()->word(), 'description' => 'Institutional buyers and stakeholders', 'audience_kind' => 'BUSINESS', 'priority' => 50, 'status' => 'ACTIVE',
        ];
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['brand_id' => $brand->id, 'organisation_id' => $brand->organisation_id]);
    }
}
