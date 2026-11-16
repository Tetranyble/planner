<?php

namespace Database\Factories;

use App\Models\AutomationRule;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AutomationRule> */
class AutomationRuleFactory extends Factory
{
    protected $model = AutomationRule::class;

    public function definition(): array
    {
        return ['organisation_id' => Organisation::factory(),
            'brand_id' => fn (array $a) => Brand::factory()->forOrganisation(Organisation::query()->findOrFail($a['organisation_id']))->create()->id,
            'owner_user_id' => User::factory(), 'name' => 'Weekly report (test)', 'trigger_kind' => 'MANUAL',
            'status' => 'DRAFT', 'max_runs_per_day' => 5];
    }
}
