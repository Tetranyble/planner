<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandClaim;
use App\Models\BrandClaimDisclaimer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandClaimDisclaimer> */
final class BrandClaimDisclaimerFactory extends Factory
{
    protected $model = BrandClaimDisclaimer::class;

    public function definition(): array
    {
        return [
            'claim_id' => BrandClaim::factory(),
            'brand_id' => fn (array $attributes) => BrandClaim::query()->findOrFail($attributes['claim_id'])->brand_id,
            'organisation_id' => fn (array $attributes) => BrandClaim::query()->findOrFail($attributes['claim_id'])->organisation_id,
            'locale' => 'en-NG', 'disclaimer_text' => 'Availability and results vary', 'required' => true,
        ];
    }

    public function forBrandClaim(BrandClaim $parent): static
    {
        return $this->state(fn () => ['claim_id' => $parent->id, 'brand_id' => $parent->brand_id, 'organisation_id' => $parent->organisation_id]);
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['claim_id' => BrandClaim::factory()->forBrand($brand),
            'brand_id' => $brand->id, 'organisation_id' => $brand->organisation_id]);
    }
}
