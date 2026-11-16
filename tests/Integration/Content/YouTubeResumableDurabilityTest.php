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
use App\Models\PublishedPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repository\Contracts\MediaPublicationPreparationRepositoryContract;
use App\Repository\Contracts\MediaPublicationRunRepositoryContract;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use App\Repository\Contracts\YouTubeTransferRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Source test for disposable MySQL 8; not executed in the authoring environment. */
final class YouTubeResumableDurabilityTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $version = ContentVersion::factory()->forItem($item)->create();
        $conn = ProviderConnection::factory()->forOrganisation($org)->forProvider('youtube')->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $conn)
            ->state(['provider' => 'youtube', 'status' => 'ACTIVE', 'external_account_id' => 'UCtest1234567890123456'])->create();
        $target = ContentTarget::factory()->forVersionAndAccount($version, $account)->create();
        $policy = BrandApprovalPolicy::factory()->forBrand($brand)->create();
        $approval = ApprovalRequest::factory()->forTarget($target, $policy, $actor)->create(['status' => 'APPROVED']);
        $job = app(PublicationIntentRepositoryContract::class)->createHeld($org, $brand, $target, $approval,
            'youtube-transfer-'.Str::uuid(), now()->subMinutes(5)->format('Y-m-d H:i:s'));
        $asset = MediaAsset::factory()->forBrand($brand)->create([
            'mime_type' => 'video/mp4', 'byte_size' => 2000000, 'sha256' => str_repeat('a', 64)]);
        $prep = app(MediaPublicationPreparationRepositoryContract::class)->create($org, $brand, $job, $asset, $approval, $actor,
            ['provider' => 'youtube', 'stages' => ['INITIALIZE_UPLOAD_SESSION', 'TRANSFER_STORAGE_BYTES']],
            'YOUTUBE_STREAM_RELEASE_DISABLED');
        $run = app(MediaPublicationRunRepositoryContract::class)->create($org, $brand, $prep, $actor);

        return [$org, $brand, $actor, $job, $asset, $run];
    }

    public function test_session_is_encrypted_and_progress_only_advances_after_google_acknowledgment(): void
    {
        [$org,$brand,$actor,$job,$asset,$run] = $this->fixture();
        $repo = app(YouTubeTransferRepositoryContract::class);
        $transfer = $repo->create($org, $brand, $run, $asset, $actor);
        self::assertSame($transfer->id, $repo->create($org, $brand, $run, $asset, $actor)->id);
        self::assertSame('HELD', $transfer->state);
        $repo->arm($transfer, $actor, 1);
        $owner = bin2hex(random_bytes(24));
        self::assertNotNull($repo->claim($transfer->id, $owner));
        $started = $repo->start($transfer->id, $owner);
        self::assertSame('INIT', $started['action']);
        $session = 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet%2Cstatus&upload_id=abcdefgh123';
        self::assertTrue($repo->finish($transfer->id, $owner, $started['attempt_id'],
            ['state' => 'SESSION_CREATED', 'session_url' => $session]));
        self::assertSame('NEEDS_PROBE', $transfer->fresh()->state);
        self::assertSame($session, $transfer->fresh()->session_url_ciphertext);
        self::assertNotSame($session, $transfer->fresh()->getRawOriginal('session_url_ciphertext'));
        $this->travel(2)->minutes();
        $owner = bin2hex(random_bytes(24));
        $repo->claim($transfer->id, $owner);
        $probe = $repo->start($transfer->id, $owner);
        self::assertSame('PROBE', $probe['action']);
        $repo->finish($transfer->id, $owner, $probe['attempt_id'], ['state' => 'OFFSET_CONFIRMED', 'offset' => 0]);
        self::assertSame('NEXT_CHUNK', $transfer->fresh()->state);
        self::assertSame(0, $transfer->fresh()->committed_bytes);
        self::assertSame(0, PublishedPost::query()->count());
    }

    public function test_expired_inflight_initiation_is_quarantined_without_a_second_send(): void
    {
        [$org,$brand,$actor,$job,$asset,$run] = $this->fixture();
        $repo = app(YouTubeTransferRepositoryContract::class);
        $transfer = $repo->create($org, $brand, $run, $asset, $actor);
        $repo->arm($transfer, $actor, 1);
        $owner = bin2hex(random_bytes(24));
        $repo->claim($transfer->id, $owner);
        $stage = $repo->start($transfer->id, $owner);
        self::assertSame('INIT', $stage['action']);
        $this->travel(5)->minutes();
        $repo->recover(20);
        self::assertSame('UNCERTAIN', $transfer->fresh()->state);
        self::assertSame('UNCERTAIN', $run->fresh()->state);
        self::assertSame('UNCERTAIN',$job->fresh()->state);
        self::assertNull($repo->claim($transfer->id,bin2hex(random_bytes(24))));
        self::assertDatabaseCount('published_posts',0);
    }
}
