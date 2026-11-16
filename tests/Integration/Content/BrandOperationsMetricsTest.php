<?php

namespace Tests\Integration\Content;

use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\Organisation;
use App\Repository\Contracts\BrandOperationsMetricsRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Execute on an isolated MySQL 8 database; must not run against production. */
final class BrandOperationsMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_respect_brand_scope_and_exclusive_time_boundaries(): void
    {
        $organisation = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($organisation)->create();
        $other = Brand::factory()->forOrganisation($organisation)->create();
        $one = ContentItem::factory()->forBrand($brand)->create();
        $one->forceFill(['created_at' => '2026-10-14 12:00:00'])->save();
        $excluded = ContentItem::factory()->forBrand($brand)->create();
        $excluded->forceFill(['created_at' => '2026-11-01 00:00:00'])->save();
        ContentItem::factory()->forBrand($other)->create();
        $repo = app(BrandOperationsMetricsRepositoryContract::class);
        $result = $repo->aggregate($organisation, $brand, '2026-10-01 00:00:00', '2026-11-01 00:00:00');
        self::assertSame(1, $result['content']['total']);
        self::assertSame(0, $result['publication_plans']['total']);
        self::assertSame(0, $result['attempts']['total']);
        self::assertSame(0, $repo->aggregate($organisation, $other, '2026-10-01 00:00:00', '2026-11-01 00:00:00')['content']['total']);
    }
}
