<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandAudienceGoal;
use App\Models\BrandAudienceSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandAudienceGoal> */
final class BrandAudienceGoalFactory extends Factory
{
    protected $model = BrandAudienceGoal::class;

    public function definition(): array
    {
        return [
            'audience_segment_id' => BrandAudienceSegment::factory(),
            'brand_id' => fn (array $attributes) => BrandAudienceSegment::query()->findOrFail($attributes['audience_segment_id'])->brand_id,
            'organisation_id' => fn (array $attributes) => BrandAudienceSegment::query()->findOrFail($attributes['audience_segment_id'])->organisation_id,
            'description' => 'Improve administrative visibility and operational efficiency', 'priority' => 50,
        ];
    }

    public function forBrandAudienceSegment(BrandAudienceSegment $parent): static
    {
        return $this->state(fn () => ['audience_segment_id' => $parent->id, 'brand_id' => $parent->brand_id, 'organisation_id' => $parent->organisation_id]);
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['audience_segment_id' => BrandAudienceSegment::factory()->forBrand($brand),
            'brand_id' => $brand->id, 'organisation_id' => $brand->organisation_id]);
    }
}
