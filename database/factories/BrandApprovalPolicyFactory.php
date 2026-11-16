<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandApprovalPolicy> */
final class BrandApprovalPolicyFactory extends Factory
{
    protected $model = BrandApprovalPolicy::class;

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'organisation_id' => fn (array $attributes) => Brand::query()->findOrFail($attributes['brand_id'])->organisation_id,
            'policy_version' => 1, 'description' => 'Initial draft policy', 'state' => 'DRAFT', 'external_publishing_requires_approval' => true, 'forbid_self_approval' => true, 'default_required_approvals' => 1, 'activated_at' => null, 'retired_at' => null,
        ];
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['brand_id' => $brand->id, 'organisation_id' => $brand->organisation_id]);
    }
}
