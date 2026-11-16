<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandAudienceLocation;
use App\Models\BrandAudienceSegment;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandAudienceLocation> */
final class BrandAudienceLocationFactory extends Factory
{
    protected $model = BrandAudienceLocation::class;

    public function definition(): array
    {
        return [
            'audience_segment_id' => BrandAudienceSegment::factory(),
            'brand_id' => fn (array $attributes) => BrandAudienceSegment::query()->findOrFail($attributes['audience_segment_id'])->brand_id,
            'organisation_id' => fn (array $attributes) => BrandAudienceSegment::query()->findOrFail($attributes['audience_segment_id'])->organisation_id,
            'country_id' => Country::factory(), 'state_id' => null, 'city_id' => null,
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
