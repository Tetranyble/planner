<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\BrandApprovalRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandApprovalRule> */
final class BrandApprovalRuleFactory extends Factory
{
    protected $model = BrandApprovalRule::class;

    public function definition(): array
    {
        return [
            'policy_id' => BrandApprovalPolicy::factory(),
            'brand_id' => fn (array $attributes) => BrandApprovalPolicy::query()->findOrFail($attributes['policy_id'])->brand_id,
            'organisation_id' => fn (array $attributes) => BrandApprovalPolicy::query()->findOrFail($attributes['policy_id'])->organisation_id,
            'rule_name' => 'Human review of public posts', 'priority' => 10, 'action_kind' => 'PUBLISH', 'platform' => null, 'content_kind' => null, 'ai_generated' => null, 'includes_sensitive_claim' => null, 'requires_human_approval' => true, 'required_approvals' => 1, 'forbid_self_approval' => true, 'is_enabled' => true,
        ];
    }

    public function forBrandApprovalPolicy(BrandApprovalPolicy $parent): static
    {
        return $this->state(fn () => ['policy_id' => $parent->id, 'brand_id' => $parent->brand_id, 'organisation_id' => $parent->organisation_id]);
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['policy_id' => BrandApprovalPolicy::factory()->forBrand($brand),
            'brand_id' => $brand->id, 'organisation_id' => $brand->organisation_id]);
    }
}
