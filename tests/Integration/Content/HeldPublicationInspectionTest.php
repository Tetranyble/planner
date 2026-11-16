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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HeldPublicationInspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inspection_repository_is_tenant_scoped_and_read_only(): void
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
        $job = $repo->createHeld($org, $brand, $target, $approval, 'da1d6b08-e68c-408e-94e9-4e4c3112e91e', '2027-02-03 12:00:00');
        $found = $repo->findForInspection($org, $brand, $job->id);
        $this->assertSame($job->id, $found->id);
        $this->assertSame('AWAITING_USER', $found->state);
        $this->assertSame(0, (int) $found->version);
        $other = Organisation::factory()->create();
        $otherBrand = Brand::factory()->forOrganisation($other)->create();
        try {
            $repo->findForInspection($other, $otherBrand, $job->id);
            $this->fail('Cross-tenant publication job was exposed.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
        $this->assertSame('AWAITING_USER', $job->fresh()->state);
    }
}
