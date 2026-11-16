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

/** MySQL integration source; requires configured Laravel and a disposable database. */
final class PublicationCalendarRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_is_brand_scoped_and_has_exclusive_upper_boundary(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $other = Brand::factory()->forOrganisation($org)->create();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $version = ContentVersion::factory()->forItem($item)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create();
        $target = ContentTarget::factory()->forVersionAndAccount($version, $account)->create();
        $policy = BrandApprovalPolicy::factory()->forBrand($brand)->create();
        $approval = ApprovalRequest::factory()->forTarget($target, $policy, User::factory()->create())->create();
        $repo = app(PublicationIntentRepositoryContract::class);
        $repo->createHeld($org, $brand, $target, $approval, '8574f84a-2e62-45a0-a70c-38b7f55f2e89', '2027-02-01 11:00:00');
        $repo->createHeld($org, $brand, $target, $approval, 'd1b4f465-8388-4a0f-8116-b6cbd04b4f49', '2027-03-01 00:00:00');
        $this->assertCount(1, $repo->between($org, $brand, '2027-02-01 00:00:00', '2027-03-01 00:00:00', 500));
        $this->assertCount(0, $repo->between($org,$other,'2027-02-01 00:00:00','2027-03-01 00:00:00',500));
    }
}
