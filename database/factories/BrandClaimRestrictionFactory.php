<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandClaimRestriction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandClaimRestriction> */
final class BrandClaimRestrictionFactory extends Factory
{
    protected $model = BrandClaimRestriction::class;

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'organisation_id' => fn (array $attributes) => Brand::query()->findOrFail($attributes['brand_id'])->organisation_id,
            'restricted_text' => 'guaranteed results', 'restriction_kind' => 'PROHIBITED_PHRASE', 'explanation' => 'Unsubstantiated absolute claim', 'active' => true,
        ];
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['brand_id' => $brand->id, 'organisation_id' => $brand->organisation_id]);
    }
}
