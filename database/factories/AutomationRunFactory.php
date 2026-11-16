<?php

namespace Database\Factories;

use App\Models\AutomationRuleVersion;
use App\Models\AutomationRun;
use App\Models\Brand;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AutomationRun> */
class AutomationRunFactory extends Factory
{
    protected $model = AutomationRun::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'organisation_id' => Organisation::factory(),
            'brand_id' => fn (array $a) => Brand::factory()->forOrganisation(Organisation::query()->findOrFail($a['organisation_id']))->create()->id,
            'automation_rule_version_id' => fn (array $a) => AutomationRuleVersion::factory()->create(['organisation_id' => $a['organisation_id'], 'brand_id' => $a['brand_id']])->id,
            'trigger_event_key' => hash('sha256', $this->faker->uuid()), 'status' => 'COMPLETED',
            'started_at' => now(), 'finished_at' => now(), 'result_summary' => json_encode(['verification' => 'USER_REPORTED_UNVERIFIED', 'aggregates_complete' => true, 'totals' => []])];
    }
}
