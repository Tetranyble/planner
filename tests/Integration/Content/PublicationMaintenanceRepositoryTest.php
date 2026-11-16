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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicationMaintenanceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_reschedule_preserves_identity_and_never_activates_dispatch(): void
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
        $repo = app(PublicationIntentRepositoryContract::class);
        $job = $repo->createHeld($org, $brand, $target, $approval, 'd01c0a89-4076-4879-926d-94cb12e325c2', '2027-02-03 12:00:00');
        $context = $repo->context($org, $brand, $job);
        $this->assertSame((string) $item->uuid, $context['item_uuid']);
        $this->assertSame((string) $target->uuid, $context['target_uuid']);
        $repo->reschedule($job, '2027-02-04 12:00:00');
        $job->refresh();
        $this->assertSame('AWAITING_USER', $job->state);
        $this->assertSame(1, (int) $job->version);
        $this->assertSame(0, (int) $job->max_attempts);
        $this->assertSame('2027-02-04 12:00:00', $job->run_at->utc()->format('Y-m-d H:i:s'));
    }
}
