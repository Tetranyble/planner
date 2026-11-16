<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandClaim;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandClaim> */
final class BrandClaimFactory extends Factory
{
    protected $model = BrandClaim::class;

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'organisation_id' => fn (array $attributes) => Brand::query()->findOrFail($attributes['brand_id'])->organisation_id,
            'claim_text' => 'A draft statement requiring factual review', 'claim_category' => 'PRODUCT_CAPABILITY', 'status' => 'DRAFT', 'valid_from' => null, 'valid_until' => null, 'approved_by_user_id' => null, 'approved_at' => null,
        ];
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['brand_id' => $brand->id, 'organisation_id' => $brand->organisation_id]);
    }
}
