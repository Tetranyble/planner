<?php

namespace Tests\Integration\Ai;

use App\Models\Brand;
use App\Models\BrandAiUsageReservation;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Ai\BrandAiUsageBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BrandAiCostSnapshotPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimate_is_stored_at_settlement_and_never_repriced_retroactively(): void
    {
        config(['services.planner_ai.cost_rates_json' => json_encode([
            'test-model' => ['input_micro_usd_per_million' => 1000000, 'output_micro_usd_per_million' => 3000000],
        ]), 'services.planner_ai.cost_rate_revision' => 'contract-2026-11']);
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $budget = app(BrandAiUsageBudget::class);
        $actor = User::factory()->create();
        $res = $budget->reserve($org, $brand, $actor, 'COPY');
        $budget->settle($res, true, 'test-model', hash('sha256', 'content'), ['input_tokens' => 200, 'output_tokens' => 100]);
        self::assertSame(500, (int) BrandAiUsageReservation::query()->findOrFail($res->id)->estimated_micro_usd);
        self::assertSame('contract-2026-11', BrandAiUsageReservation::query()->findOrFail($res->id)->cost_rate_revision);
        config(['services.planner_ai.cost_rates_json' => '']);
        self::assertSame(500, $budget->usage($org, $brand)['provider_token_evidence']['cost_estimate']['micro_usd_observed']);
        $res2 = $budget->reserve($org, $brand, $actor, 'CAMPAIGN');
        $budget->settle($res2, true, 'unknown-model', hash('sha256', 'content2'));
        self::assertSame(1, $budget->usage($org, $brand)['provider_token_evidence']['cost_estimate']['unpriced_generation_count']);
    }
}
