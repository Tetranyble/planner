<?php

namespace Database\Factories;

use App\Models\AutomationRule;
use App\Models\AutomationRuleVersion;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AutomationRuleVersion> */
class AutomationRuleVersionFactory extends Factory
{
    protected $model = AutomationRuleVersion::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'organisation_id' => Organisation::factory(),
            'brand_id' => fn (array $a) => Brand::factory()->forOrganisation(Organisation::query()->findOrFail($a['organisation_id']))->create()->id,
            'automation_rule_id' => fn (array $a) => AutomationRule::factory()->create(['organisation_id' => $a['organisation_id'], 'brand_id' => $a['brand_id']])->id,
            'version_number' => 1, 'trigger_config' => json_encode(['kind' => 'MANUAL']),
            'condition_config' => json_encode(['from' => now('UTC')->subDay()->toDateString(), 'to' => now('UTC')->toDateString()]),
            'action_plan' => json_encode(['kind' => 'METRICS_REPORT_ONLY']),
            'policy_snapshot' => json_encode(['publishing_allowed' => false]),
            'config_hash' => hash('sha256', 'report-only-test'), 'created_by_user_id' => User::factory()];
    }
}
