<?php

namespace Tests\Integration\Analytics;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\ProviderMetricSyncPolicyRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

final class ProviderMetricSyncScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_is_scoped_and_optimistically_versioned(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $other = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $repo = app(ProviderMetricSyncPolicyRepositoryContract::class);
        $first = DB::transaction(fn () => $repo->upsert($org, $brand, $actor, 8, 10, false, 0));
        self::assertSame(1, $first->version);
        self::assertNull($repo->get($org, $other));
        $second = DB::transaction(fn () => $repo->upsert($org, $brand, $actor, 9, 5, true, 1));
        self::assertSame(2, $second->version);
        self::assertSame(9, (int) $second->utc_hour);
        $this->expectException(ConflictHttpException::class);
        DB::transaction(fn () => $repo->upsert($org, $brand, $actor, 10, 10, true, 1));
    }
}
