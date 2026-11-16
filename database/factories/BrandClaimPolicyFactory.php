<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandClaimPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandClaimPolicy> */
final class BrandClaimPolicyFactory extends Factory
{
    protected $model = BrandClaimPolicy::class;

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'organisation_id' => fn (array $attributes) => Brand::query()->findOrFail($attributes['brand_id'])->organisation_id,
            'approved_claims_only' => true, 'evidence_required_for_quantified' => true, 'evidence_required_for_comparisons' => true, 'allow_guaranteed_outcome_claims' => false, 'require_review_for_new_claims' => true,
        ];
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['brand_id' => $brand->id, 'organisation_id' => $brand->organisation_id]);
    }
}
