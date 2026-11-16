<?php

namespace Tests\Integration\Analytics;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Eloquent\ReportAutomationRepository;
use App\Services\Automation\ManageReportAutomations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Requires real MySQL. Exercise the same transactional execution path as cron. */
final class ScheduledReportAutomationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_opt_in_daily_rule_is_scoped_and_deduplicated_per_utc_day(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $other = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $repo = app(ReportAutomationRepository::class);
        $rule = $repo->createRule($org, $brand, $actor->id, 'Daily evidence report', 2);
        $config = ['trigger_kind' => 'DAILY_UTC', 'hour_utc' => 0, 'lookback_days' => 7];
        $version = $repo->createVersion($org, $brand, $rule, $actor->id, $config, hash('sha256', json_encode($config)));
        $repo->setStatus($rule, 'ENABLED');
        $this->assertNull($repo->rule($org, $other, $rule->id));
        $this->assertTrue($repo->scheduledCandidates(20)->contains('id', $rule->id));
        $service = app(ManageReportAutomations::class);
        $dry = $service->processDue(20, true);
        $this->assertGreaterThanOrEqual(1, $dry['created']);
        $this->assertSame(0, $repo->runCountToday($org, $brand, $version));
        $first = $service->processDue(20, false);
        $this->assertSame(1, $first['created']);
        $second = $service->processDue(20, false);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1,$repo->runCountToday($org,$brand,$version));
    }
}
