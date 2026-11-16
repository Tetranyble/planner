<?php

namespace Tests\Integration\Content;

use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\ContentVersion;
use App\Models\MediaAsset;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repository\Contracts\MediaPublicationPreparationRepositoryContract;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Run on disposable MySQL; test source is not a claim of execution. */
final class MediaPublicationPreparationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $version = ContentVersion::factory()->forItem($item)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create();
        $target = ContentTarget::factory()->forVersionAndAccount($version, $account)->create();
        $policy = BrandApprovalPolicy::factory()->forBrand($brand)->create();
        $user = User::factory()->create();
        $approval = ApprovalRequest::factory()->forTarget($target, $policy, $user)->create();
        $job = app(PublicationIntentRepositoryContract::class)->createHeld($org, $brand, $target, $approval,
            'media-prep-'.Str::uuid(), '2027-01-01 12:00:00');
        $asset = MediaAsset::factory()->forBrand($brand)->create(['mime_type' => 'image/jpeg']);

        return [$org, $brand, $user, $job, $approval, $asset];
    }

    public function test_preparation_is_brand_scoped_and_abort_is_audited(): void
    {
        [$org,$brand,$user,$job,$approval,$asset] = $this->fixture();
        $repo = app(MediaPublicationPreparationRepositoryContract::class);
        $plan = ['provider' => 'instagram', 'mode' => 'IMAGE_CONTAINER', 'stages' => ['CREATE_CONTAINER'],
            'remote_dispatch_allowed' => false];
        $prepared = $repo->create($org, $brand, $job, $asset, $approval, $user, $plan, 'PROVIDER_MEDIA_DELIVERY_NOT_RELEASED');
        $this->assertSame('PREPARED', $prepared->state);
        $this->assertCount(1, $repo->list($org, $brand, 20));
        $this->assertDatabaseCount('media_publication_preparation_events', 1);
        $repo->abort($prepared, $user);
        $this->assertSame('ABORTED', $prepared->fresh()->state);
        $this->assertDatabaseCount('media_publication_preparation_events', 2);
        $other = Brand::factory()->forOrganisation($org)->create();
        $this->assertCount(0, $repo->list($org, $other, 20));
        $this->expectException(ModelNotFoundException::class);
        $repo->find($org,$other,$prepared->id);
    }
}
