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
use App\Repository\Contracts\ContentApprovalRepositoryContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Needs real MySQL 8 for the generated pending marker constraint and composite tenant FKs. */
final class ApprovalRequestPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_cannot_fetch_target_from_other_brand_and_only_one_pending_request_per_target(): void
    {
        $org = Organisation::factory()->create();
        $a = Brand::factory()->forOrganisation($org)->create();
        $b = Brand::factory()->forOrganisation($org)->create();
        $item = ContentItem::factory()->forBrand($b)->create();
        $v = ContentVersion::factory()->forItem($item)->create();
        $conn = ProviderConnection::factory()->forOrganisation($org)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($b, $conn)->create();
        $target = ContentTarget::factory()->forVersionAndAccount($v, $account)->create();
        $policy = BrandApprovalPolicy::factory()->forBrand($b)->create();
        $owner = User::factory()->create();
        $repo = app(ContentApprovalRepositoryContract::class);
        $row = ApprovalRequest::factory()->forTarget($target, $policy, $owner)->create();
        $this->assertDatabaseHas('approval_requests', ['id' => $row->id, 'status' => 'PENDING']);
        $this->expectException(QueryException::class);
        ApprovalRequest::factory()->forTarget($target, $policy, User::factory()->create())->create();
    }

    public function test_scoped_target_lookup_rejects_other_brand_uuid(): void
    {
        $org = Organisation::factory()->create();
        $a = Brand::factory()->forOrganisation($org)->create();
        $b = Brand::factory()->forOrganisation($org)->create();
        $item = ContentItem::factory()->forBrand($b)->create();
        $v = ContentVersion::factory()->forItem($item)->create();
        $conn = ProviderConnection::factory()->forOrganisation($org)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($b, $conn)->create();
        $target = ContentTarget::factory()->forVersionAndAccount($v, $account)->create();
        $foreign = ContentItem::factory()->forBrand($a)->create();
        $this->expectException(ModelNotFoundException::class);
        app(ContentApprovalRepositoryContract::class)->target($org, $a, $foreign, $target->uuid);
    }
}
