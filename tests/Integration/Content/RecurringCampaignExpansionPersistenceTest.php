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
use App\Services\Scheduling\ExpandRecurringCampaignOccurrences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Requires a disposable MySQL 8 database and actual Laravel dependencies. */
final class RecurringCampaignExpansionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $owner = User::factory()->create();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $version = ContentVersion::factory()->forItem($item)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create();
        $target = ContentTarget::factory()->forVersionAndAccount($version, $account)->create();
        $repo = app(RecurringCampaignRepositoryContract::class);
        $schedule = $repo->create($org, $brand, $target->id, $owner->id, [
            'rrule' => 'FREQ=DAILY;INTERVAL=1', 'timezone' => 'Africa/Lagos',
            'start_local' => now('Africa/Lagos')->addDays(2)->startOfHour()->format('Y-m-d H:i:s'),
            'end_at' => null, 'max_occurrences' => 3,
        ]);

        return [$org, $brand, $schedule, $repo];
    }

    public function test_opt_in_projection_is_bounded_idempotent_and_never_creates_a_publication_job(): void
    {
        [$org, $brand, $schedule, $repo] = $this->fixture();
        $worker = app(ExpandRecurringCampaignOccurrences::class);
        config()->set('planner.recurring_expansion_enabled', false);
        $this->assertSame(0, $worker->run(10)['processed']);
        $this->assertDatabaseCount('recurring_campaign_occurrences', 0);
        config()->set('planner.recurring_expansion_enabled', true);
        $this->assertSame(3, $worker->run(10)['suggested']);
        $this->assertDatabaseCount('recurring_campaign_occurrences', 3);
        $this->assertDatabaseCount('publication_jobs', 0);
        $this->assertSame(3, $repo->projected($org, $brand, $schedule)->count());
        $schedule->forceFill(['next_expand_at' => now()->subSecond()])->save();
        $worker->run(10);
        $this->assertDatabaseCount('recurring_campaign_occurrences', 3);
        $this->assertDatabaseCount('publication_jobs', 0);
        $first = $repo->projected($org, $brand, $schedule)->first();
        $this->assertNull($first->held_at);
        $repo->markHeldProjection($schedule, [
            'occurrence_key' => $first->occurrence_key, 'local' => $first->local_at,
            'run_at' => $first->run_at->utc()->format('Y-m-d\TH:i:s\Z'),
        ]);
        $this->assertNotNull($first->fresh()->held_at);
        $this->assertDatabaseCount('publication_jobs', 0); // Projection cannot approve itself.
    }

    public function test_pause_prevents_expansion_and_other_brands_cannot_read_projection(): void
    {
        [$org, $brand, $schedule, $repo] = $this->fixture();
        $otherBrand = Brand::factory()->forOrganisation($org)->create();
        $repo->transition($schedule, 'PAUSED');
        config()->set('planner.recurring_expansion_enabled', true);
        $this->assertSame(0, app(ExpandRecurringCampaignOccurrences::class)->run(10)['processed']);
        $repo->transition($schedule, 'ACTIVE');
        app(ExpandRecurringCampaignOccurrences::class)->run(10);
        $this->assertCount(0, $repo->projected($org, $otherBrand, $schedule));
        $this->assertCount(3, $repo->projected($org, $brand, $schedule));
    }
}
