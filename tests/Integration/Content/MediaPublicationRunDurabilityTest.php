<?php

namespace Tests\Integration\Content;

use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\ContentVersion;
use App\Models\MediaAsset;
use App\Models\MediaPublicationRun;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repository\Contracts\MediaPublicationPreparationRepositoryContract;
use App\Repository\Contracts\MediaPublicationRunRepositoryContract;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Only run on a disposable MySQL 8 database; it relies on row locks and composite FKs. */
final class MediaPublicationRunDurabilityTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $version = ContentVersion::factory()->forItem($item)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider('instagram')->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)
            ->state(['provider' => 'instagram', 'status' => 'ACTIVE', 'external_account_id' => '123456789'])->create();
        $target = ContentTarget::factory()->forVersionAndAccount($version, $account)->create();
        $policy = BrandApprovalPolicy::factory()->forBrand($brand)->create();
        $approval = ApprovalRequest::factory()->forTarget($target, $policy, $actor)->create(['status' => 'APPROVED']);
        $job = app(PublicationIntentRepositoryContract::class)->createHeld($org, $brand, $target, $approval,
            'stage-'.Str::uuid(), now()->subMinutes(2)->format('Y-m-d H:i:s'));
        $asset = MediaAsset::factory()->forBrand($brand)->create(['mime_type' => 'image/jpeg']);
        $prep = app(MediaPublicationPreparationRepositoryContract::class)->create($org, $brand, $job, $asset, $approval, $actor,
            ['provider' => 'instagram', 'mode' => 'IMAGE_CONTAINER', 'stages' => ['CREATE_CONTAINER'], 'remote_dispatch_allowed' => false],
            'MEDIA_DELIVERY_NOT_RELEASED');

        return [$org, $brand, $actor, $job, $prep];
    }

    public function test_stages_are_fenced_persisted_and_never_replayed_after_an_uncertain_send(): void
    {
        [$org,$brand,$actor,$job,$prep] = $this->fixture();
        $repo = app(MediaPublicationRunRepositoryContract::class);
        $run = $repo->create($org, $brand, $prep, $actor);
        $this->assertSame($run->id, $repo->create($org, $brand, $prep, $actor)->id);
        $repo->arm($run, $actor, 1);
        $owner = bin2hex(random_bytes(24));
        $claim = $repo->claim($run->id, $owner);
        $this->assertNotNull($claim);
        $started = $repo->startStage($run->id, $owner);
        $this->assertSame('CREATE_CONTAINER', $started['operation']);
        $this->assertTrue($repo->stageIsFenced($run->id, $owner, $started['attempt_id']));
        $this->assertTrue($repo->finishStage($run->id, $owner, $started['attempt_id'],
            ['state' => 'UNCERTAIN', 'code' => 'REMOTE_RESPONSE_UNKNOWN']));
        $this->assertSame('UNCERTAIN', $run->fresh()->state);
        $this->assertNull($repo->claim($run->id, bin2hex(random_bytes(24))));
        $this->assertDatabaseCount('media_publication_stage_attempts', 1);
        $this->assertDatabaseCount('published_posts', 0);
        $this->assertSame('UNCERTAIN', $job->fresh()->state);
        $this->assertDatabaseHas('publication_attempts', ['publication_job_id' => $job->id, 'result' => 'UNCERTAIN']);
    }

    public function test_container_receipt_is_encrypted_and_requires_later_verification(): void
    {
        [$org,$brand,$actor,$job,$prep] = $this->fixture();
        $repo = app(MediaPublicationRunRepositoryContract::class);
        $run = $repo->create($org, $brand, $prep, $actor);
        $repo->arm($run, $actor, 1);
        $owner = bin2hex(random_bytes(24));
        $repo->claim($run->id, $owner);
        $stage = $repo->startStage($run->id, $owner);
        $repo->finishStage($run->id, $owner, $stage['attempt_id'],
            ['state' => 'CONTAINER_CREATED', 'provider_receipt' => '123456789']);
        $stored = MediaPublicationRun::query()->findOrFail($run->id);
        $this->assertSame('NEXT_STAGE', $stored->state);
        $this->assertSame('CHECK_CONTAINER_STATUS', $stored->next_operation);
        $this->assertSame('123456789', $stored->provider_operation_reference);
        $this->assertNotSame('123456789', $stored->getRawOriginal('provider_operation_reference'));
        $this->assertDatabaseCount('published_posts', 0);
        $this->assertDatabaseCount('media_publication_run_events', 5);
    }

    /** Exercises the complete repository state machine without any HTTP transport. */
    public function test_second_verified_provider_read_atomically_persists_the_post_and_prevents_replay(): void
    {
        [$org,$brand,$actor,$job,$prep] = $this->fixture();
        $repo = app(MediaPublicationRunRepositoryContract::class);
        $run = $repo->create($org, $brand, $prep, $actor);
        $repo->arm($run, $actor, 1);
        $advance = function (string $expected, array $outcome) use ($repo, $run): void {
            $this->travel(11)->minutes();
            $owner = bin2hex(random_bytes(24));
            $this->assertNotNull($repo->claim($run->id, $owner));
            $stage = $repo->startStage($run->id, $owner);
            $this->assertSame($expected, $stage['operation']);
            $this->assertTrue($repo->stageIsFenced($run->id, $owner, $stage['attempt_id']));
            $this->assertTrue($repo->finishStage($run->id, $owner, $stage['attempt_id'], $outcome));
        };
        $advance('CREATE_CONTAINER', ['state' => 'CONTAINER_CREATED', 'provider_receipt' => '123456789']);
        $advance('CHECK_CONTAINER_STATUS', ['state' => 'READY_TO_FINALIZE']);
        $advance('PUBLISH_CONTAINER', ['state' => 'PUBLISHED', 'provider_receipt' => '987654321']);
        $this->assertSame('AWAITING_VERIFICATION', $run->fresh()->state);
        $this->assertDatabaseCount('published_posts', 0);
        $this->assertDatabaseCount('publication_attempts', 0);
        $advance('VERIFY_MEDIA_RECEIPT', ['state' => 'VERIFIED', 'provider_receipt' => '987654321',
            'provider_owner_id' => '123456789']);
        $this->assertSame('COMPLETED', $run->fresh()->state);
        $this->assertSame('PUBLISHED', $job->fresh()->state);
        $this->assertDatabaseCount('published_posts', 1);
        $this->assertDatabaseCount('publication_attempts', 1);
        $this->assertDatabaseHas('published_posts', ['publication_job_id' => $job->id,
            'external_post_id' => '987654321', 'provider' => 'instagram']);
        $this->assertDatabaseHas('publication_events', ['publication_job_id' => $job->id,
            'event_type' => 'MEDIA_POST_VERIFIED']);
        $this->assertNull($repo->claim($run->id, bin2hex(random_bytes(24))));
    }

    public function test_verified_receipt_from_different_instagram_owner_is_quarantined(): void
    {
        [$org,$brand,$actor,$job,$prep] = $this->fixture();
        $repo = app(MediaPublicationRunRepositoryContract::class);
        $run = $repo->create($org, $brand, $prep, $actor);
        $repo->arm($run, $actor, 1);
        $advance = function (string $expected, array $outcome) use ($repo, $run): void {
            $this->travel(11)->minutes();
            $owner = bin2hex(random_bytes(24));
            $this->assertNotNull($repo->claim($run->id, $owner));
            $stage = $repo->startStage($run->id, $owner);
            $this->assertSame($expected, $stage['operation']);
            $this->assertTrue($repo->finishStage($run->id, $owner, $stage['attempt_id'], $outcome));
        };
        $advance('CREATE_CONTAINER', ['state' => 'CONTAINER_CREATED', 'provider_receipt' => '123456789']);
        $advance('CHECK_CONTAINER_STATUS', ['state' => 'READY_TO_FINALIZE']);
        $advance('PUBLISH_CONTAINER',['state' => 'PUBLISHED', 'provider_receipt' => '987654321']);
        $advance('VERIFY_MEDIA_RECEIPT',['state' => 'VERIFIED', 'provider_receipt' => '987654321',
            'provider_owner_id' => '998877665']);
        $this->assertSame('UNCERTAIN',$run->fresh()->state);
        $this->assertSame('UNCERTAIN',$job->fresh()->state);
        $this->assertDatabaseCount('published_posts',0);
        $this->assertDatabaseCount('publication_attempts',1);
    }
}
