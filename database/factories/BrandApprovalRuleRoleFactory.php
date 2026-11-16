<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandApprovalRule;
use App\Models\BrandApprovalRuleRole;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Tetranyble\Kinship\Models\Role;

/** @extends Factory<BrandApprovalRuleRole> */
final class BrandApprovalRuleRoleFactory extends Factory
{
    protected $model = BrandApprovalRuleRole::class;

    public function definition(): array
    {
        return [
            'approval_rule_id' => BrandApprovalRule::factory(),
            'brand_id' => fn (array $attributes) => BrandApprovalRule::query()->findOrFail($attributes['approval_rule_id'])->brand_id,
            'organisation_id' => fn (array $attributes) => BrandApprovalRule::query()->findOrFail($attributes['approval_rule_id'])->organisation_id,
            'reviewer_role_id' => fn (array $a) => Role::factory()->forWorkspace(Organisation::query()->findOrFail($a['organisation_id']))->create()->id,
        ];
    }

    public function forBrandApprovalRule(BrandApprovalRule $parent): static
    {
        return $this->state(fn () => ['approval_rule_id' => $parent->id, 'brand_id' => $parent->brand_id, 'organisation_id' => $parent->organisation_id]);
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['approval_rule_id' => BrandApprovalRule::factory()->forBrand($brand),
            'brand_id' => $brand->id, 'organisation_id' => $brand->organisation_id]);
    }
}
