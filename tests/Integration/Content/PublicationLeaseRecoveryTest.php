<?php

namespace Tests\Integration\Content;

use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\ContentVersion;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use App\Repository\Contracts\PublicationOperationsRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Requires real disposable MySQL 8; authoring is not execution. */
final class PublicationLeaseRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function sampleJob(): array
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $version = ContentVersion::factory()->forItem($item)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create();
        $target = ContentTarget::factory()->forVersionAndAccount($version, $account)->create();
        $policy = BrandApprovalPolicy::factory()->forBrand($brand)->create();
        $approval = ApprovalRequest::factory()->forTarget($target, $policy, User::factory()->create())->create();
        $job = app(PublicationIntentRepositoryContract::class)->createHeld($org, $brand, $target, $approval,
            '93011d52-64d3-49d7-b65f-d76abeb1a374', '2027-01-01 12:00:00');

        return [$org, $brand, $job];
    }

    public function test_stale_publishing_becomes_uncertain_once_with_event_and_no_retry(): void
    {
        [$org,$brand,$job] = $this->sampleJob();
        $job->forceFill(['state' => 'PUBLISHING', 'lease_owner' => 'expired-worker',
            'lease_until' => now()->subMinutes(10), 'max_attempts' => 5, 'attempt_count' => 1])->save();
        $repo = app(PublicationOperationsRepositoryContract::class);
        $this->assertContains($job->id, $repo->staleLeaseIds(50));
        $change = $repo->recoverStale($job->id);
        $this->assertSame('UNCERTAIN', $change['to']);
        $this->assertSame('UNCERTAIN', $job->fresh()->state);
        $this->assertNull($job->fresh()->lease_owner);
        $this->assertNull($repo->recoverStale($job->id));
        $this->assertDatabaseCount('publication_events', 1);
        $overview = $repo->dashboard($org, $brand, 20);
        $this->assertSame(1, (int) $overview['counts']['UNCERTAIN']);
        $this->assertFalse($overview['dispatch_enabled']);
    }

    public function test_nonexpired_lease_is_not_mutated(): void
    {
        [,,$job] = $this->sampleJob();
        $job->forceFill(['state' => 'LEASED', 'lease_until' => now()->addMinutes(10)])->save();
        $repo = app(PublicationOperationsRepositoryContract::class);
        $this->assertNotContains($job->id, $repo->staleLeaseIds(50));
        $this->assertNull($repo->recoverStale($job->id));
        $this->assertDatabaseCount('publication_events',0);
    }
}
