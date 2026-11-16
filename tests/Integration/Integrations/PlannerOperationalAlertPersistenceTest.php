<?php

namespace Tests\Integration\Integrations;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerOperationalAlert;
use App\Models\PlannerOperationalAlertEvent;
use App\Repository\Contracts\PlannerOperationalAlertRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlannerOperationalAlertPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_idempotent_alert_reopen_and_resolution_do_not_cross_brands(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $other = Brand::factory()->forOrganisation($org)->create();
        $repo = app(PlannerOperationalAlertRepositoryContract::class);
        self::assertSame(1, $repo->reconcile($org, $brand, ['publication_uncertain' => 2]));
        self::assertSame(0, $repo->reconcile($org, $brand, ['publication_uncertain' => 2]));
        self::assertSame([], $repo->list($org, $other));
        self::assertSame(1, $repo->reconcile($org, $brand, ['publication_uncertain' => 0]));
        self::assertSame(1, $repo->reconcile($org, $brand, ['publication_uncertain' => 1]));
        self::assertSame(1, PlannerOperationalAlert::query()->where('brand_id', $brand->id)->count());
        $events = PlannerOperationalAlertEvent::query()->where('brand_id', $brand->id)
            ->orderBy('id')->pluck('event_type')->all();
        self::assertSame(['OPENED', 'RESOLVED', 'REOPENED'], $events);
        self::assertSame('OPEN', $repo->list($org,$brand)[0]['state']);
    }
}
