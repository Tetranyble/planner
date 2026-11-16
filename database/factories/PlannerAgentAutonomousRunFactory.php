<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerAgentAutonomousRun;
use App\Models\PlannerAgentDelegation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PlannerAgentAutonomousRun> */
final class PlannerAgentAutonomousRunFactory extends Factory
{
    protected $model = PlannerAgentAutonomousRun::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'organisation_id' => Organisation::factory(),
            'brand_id' => fn (array $a) => Brand::factory()->create(['organisation_id' => $a['organisation_id']])->id,
            'requested_by_user_id' => User::factory(),
            'delegation_id' => fn (array $a) => PlannerAgentDelegation::factory()->create([
                'organisation_id' => $a['organisation_id'], 'brand_id' => $a['brand_id'],
                'user_id' => $a['requested_by_user_id']])->id,
            'idempotency_key' => 'research-'.Str::random(22), 'request_hash' => str_repeat('a', 64),
            'title' => $this->faker->sentence(), 'objective' => $this->faker->paragraph(),
            'resource_kind' => 'SOCIAL_COPY', 'status' => 'QUEUED_RESEARCH'];
    }
}
