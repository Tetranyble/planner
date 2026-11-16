<?php

namespace Tests\Integration\Content;

use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\ContentVersion;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repository\Contracts\RecurringCampaignRepositoryContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MySQL/Kinship runtime required. Authored only; not executed in this archive. */
final class RecurringCampaignRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_is_brand_scoped_versioned_and_retires_irreversibly(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $other = Brand::factory()->forOrganisation($org)->create();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $version = ContentVersion::factory()->forItem($item)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create();
        $target = ContentTarget::factory()->forVersionAndAccount($version, $account)->create();
        $repo = app(RecurringCampaignRepositoryContract::class);
        $this->assertSame((int) $target->id, $repo->ownsTarget($org, $brand, (string) $target->uuid));
        $this->assertNull($repo->ownsTarget($org, $other, (string) $target->uuid));
        $schedule = $repo->create($org, $brand, (int) $target->id, (int) User::factory()->create()->id, [
            'rrule' => 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO', 'timezone' => 'Africa/Lagos',
            'start_local' => '2027-01-04 09:00:00', 'end_at' => null, 'max_occurrences' => 40,
        ]);
        $this->assertSame(0, (int) $schedule->version);
        $this->assertSame((string) $target->uuid, (string) $repo->find($org, $brand, (int) $schedule->id)->target->uuid);
        $repo->transition($schedule, 'PAUSED');
        $this->assertSame(1, (int) $schedule->version);
        $this->assertSame('PAUSED', $schedule->state);
        $this->expectException(ModelNotFoundException::class);
        $repo->find($org, $other, (int) $schedule->id);
    }
}
