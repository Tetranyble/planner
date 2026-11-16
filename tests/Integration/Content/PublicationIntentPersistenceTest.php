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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MySQL 8 integration; intentionally not executed until the runtime gate is available. */
final class PublicationIntentPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_held_intent_is_scoped_idempotent_and_cancellable(): void
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
        $key = 'f09d514f-7509-4a34-a5c2-d13612dc8ab1';
        $job = $repo->createHeld($org, $brand, $target, $approval, $key, '2027-01-01 12:00:00');
        $this->assertSame('AWAITING_USER', $job->state);
        $this->assertSame(0, (int) $job->max_attempts);
        $this->assertSame($job->id, $repo->findByOccurrence($org, $brand, $target, $key)->id);
        $this->assertCount(1, $repo->listing($org, $brand, 100));
        $this->assertCount(0, $repo->listing($org, $other, 100));
        $this->expectException(ModelNotFoundException::class);
        $repo->findLocked($org, $other, $job->id);
    }

    public function test_unique_occurrence_constraint_prevents_duplicate_insert(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $v = ContentVersion::factory()->forItem($item)->create();
        $conn = ProviderConnection::factory()->forOrganisation($org)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $conn)->create();
        $target = ContentTarget::factory()->forVersionAndAccount($v, $account)->create();
        $policy = BrandApprovalPolicy::factory()->forBrand($brand)->create();
        $approval = ApprovalRequest::factory()->forTarget($target, $policy, User::factory()->create())->create();
        $repo = app(PublicationIntentRepositoryContract::class);
        $key = '1c059754-ce5b-4fe8-9522-8710a26de011';
        $repo->createHeld($org, $brand, $target, $approval, $key, '2027-01-01 12:00:00');
        $this->expectException(QueryException::class);
        $repo->createHeld($org,$brand,$target,$approval,$key,'2027-01-01 12:00:00');
    }
}
