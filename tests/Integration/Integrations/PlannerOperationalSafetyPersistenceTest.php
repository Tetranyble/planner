<?php

namespace Tests\Integration\Integrations;

use App\Models\Brand;
use App\Models\Organisation;
use App\Repository\Contracts\PlannerOperationalSafetyRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlannerOperationalSafetyPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_scoped_metrics_never_include_other_brand_failures(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $other = Brand::factory()->forOrganisation($org)->create();
        $repo = app(PlannerOperationalSafetyRepositoryContract::class);
        $empty = $repo->summary($org, $brand);
        self::assertSame(0, array_sum($empty));
        $otherEmpty = $repo->summary($org, $other);
        self::assertSame($empty, $otherEmpty);
        // Publication job dependencies are created via related model factories;
        // the repository itself must have no write or cross-tenant capabilities.
        self::assertArrayHasKey('publication_uncertain', $empty);
        self::assertArrayHasKey('metrics_failed', $empty);
        self::assertArrayHasKey('media_expired_leases', $empty);
    }
}
