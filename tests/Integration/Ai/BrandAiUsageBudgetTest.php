<?php

namespace Tests\Integration\Ai;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Ai\BrandAiUsageBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Run on disposable MySQL 8 alongside the full AI service suite. */
final class BrandAiUsageBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_ambiguous_external_attempt_stays_charged_against_daily_limit(): void
    {
        config(['services.planner_ai.daily_brand_limit' => 1]);
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $budget = app(BrandAiUsageBudget::class);
        $reservation = $budget->reserve($org, $brand, $actor, 'CREATIVE_IMAGE_PREVIEW');
        $budget->markUncertain($reservation);
        $budget->markUncertain($reservation); // idempotent; no accidental quota release
        self::assertSame(1, $budget->usage($org, $brand)['uncertain']);
        self::assertSame(0, $budget->usage($org, $brand)['remaining']);
        $this->expectException(ValidationException::class);
        $budget->reserve($org, $brand, $actor, 'AGENT_RESOURCE');
    }

    public function test_budget_is_shared_by_copy_and_campaign_and_scoped_by_brand(): void
    {
        config(['services.planner_ai.daily_brand_limit' => 2]);
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $second = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $budget = app(BrandAiUsageBudget::class);
        $first = $budget->reserve($org, $brand, $actor, 'COPY');
        self::assertSame(1, $budget->usage($org, $brand)['reserved']);
        $budget->settle($first, true, 'mock', hash('sha256', 'output'));
        $budget->settle($first, false); // idempotent late failure cannot reverse success
        $secondReservation = $budget->reserve($org, $brand, $actor, 'CAMPAIGN');
        self::assertSame(0, $budget->usage($org, $brand)['remaining']);
        try {
            $budget->reserve($org, $brand, $actor, 'COPY');
            self::fail('The third generation should be denied');
        } catch (ValidationException $expected) {
            self::assertSame(2, $budget->usage($org, $brand)['succeeded'] + $budget->usage($org, $brand)['reserved']);
        }
        $budget->settle($secondReservation, false);
        self::assertSame(1, $budget->usage($org, $brand)['remaining']);
        self::assertSame(2, $budget->usage($org, $second)['remaining']);
        self::assertSame(1, $budget->usage($org, $brand)['failed']);
        self::assertSame(0, DB::table('publication_jobs')->count());
    }

    public function test_provider_token_counters_are_observed_but_unknown_is_not_zero(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $budget = app(BrandAiUsageBudget::class);
        $first = $budget->reserve($org, $brand, $actor, 'COPY');
        $budget->settle($first, true, 'test-model', hash('sha256', 'copy'),
            ['input_tokens' => 101, 'output_tokens' => 39]);
        $second = $budget->reserve($org, $brand, $actor, 'CAMPAIGN');
        $budget->settle($second, true, 'test-model', hash('sha256', 'campaign'));
        $usage = $budget->usage($org, $brand)['provider_token_evidence'];
        self::assertSame(101,$usage['input_tokens_observed']);
        self::assertSame(39,$usage['output_tokens_observed']);
        self::assertSame(1,$usage['usage_unreported_count']);
        self::assertFalse($usage['currency_cost_available']);
    }
}
