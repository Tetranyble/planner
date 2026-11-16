<?php

namespace Tests\Integration\Integrations;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\PlannerAlertMailDelivery;
use App\Models\User;
use App\Repository\Contracts\PlannerAlertMailRepositoryContract;
use App\Repository\Contracts\PlannerOperationalAlertRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlannerAlertMailPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_scoped_alert_email_is_durable_and_uncertain_never_requeued(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $org = Organisation::factory()->create(['created_by_user_id' => $owner->id]);
        OrganisationMembership::factory()->forOrganisation($org)->forUser($owner)->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $other = Brand::factory()->forOrganisation($org)->create();
        app(PlannerOperationalAlertRepositoryContract::class)->reconcile($org, $brand, ['publication_uncertain' => 1]);
        $mail = app(PlannerAlertMailRepositoryContract::class);
        self::assertSame(1, $mail->queue(10));
        self::assertSame(0, $mail->queue(10));
        $lease = $mail->claim();
        self::assertNotNull($lease);
        self::assertSame((int) $brand->id, $lease['brand_id']);
        self::assertSame(0, $mail->summary((int) $org->id, (int) $other->id)['queued']);
        self::assertFalse($mail->finish($lease['id'], 'wrong-owner', true));
        self::assertTrue($mail->finish($lease['id'], $lease['owner'], false));
        self::assertNull($mail->claim());
        self::assertSame(1, $mail->summary((int) $org->id, (int) $brand->id)['uncertain']);
        self::assertSame(1, PlannerAlertMailDelivery::query()->count());
    }
}
