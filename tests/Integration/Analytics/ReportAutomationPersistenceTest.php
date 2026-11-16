<?php

namespace Tests\Integration\Analytics;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Eloquent\ReportAutomationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Requires disposable MySQL + migrated Kinship schema. Not executed in archive environment. */
final class ReportAutomationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_versions_and_runs_remain_brand_scoped_and_idempotent(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $other = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $repo = app(ReportAutomationRepository::class);
        $rule = $repo->createRule($org, $brand, $actor->id, 'Performance report', 3);
        $version = $repo->createVersion($org, $brand, $rule, $actor->id, ['from' => '2026-11-01', 'to' => '2026-11-03'], str_repeat('a', 64));
        $this->assertSame($rule->id, $repo->rule($org, $brand, $rule->id)->id);
        $this->assertNull($repo->rule($org, $other, $rule->id));
        $this->assertNull($repo->latestVersion($org, $other, $rule));
        $repo->setStatus($rule, 'ENABLED');
        $run = $repo->createRun($org, $brand, $version, hash('sha256', 'idempotency-key'), ['verification' => 'USER_REPORTED_UNVERIFIED', 'aggregates_complete' => true, 'totals' => []]);
        $this->assertSame($run->id, $repo->run($org, $brand, $version, hash('sha256', 'idempotency-key'))->id);
        $this->assertNull($repo->run($org, $other, $version, hash('sha256', 'idempotency-key')));
        $this->assertSame(1,$repo->runCountToday($org,$brand,$version));
    }
}
