<?php

namespace Tests\Integration\Analytics;

use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\ContentVersion;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\PublishedPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repository\Contracts\ProviderMetricSyncRepositoryContract;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Full MySQL 8 FK and lease tests. Source authored; run locally on a disposable database. */
final class ProviderMetricSyncDurabilityTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $version = ContentVersion::factory()->forItem($item)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)
            ->forProvider('youtube')->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)
            ->state(['status' => 'ACTIVE', 'external_account_id' => 'UC'.str_repeat('a', 22)])->create();
        $target = ContentTarget::factory()->forVersionAndAccount($version, $account)->create();
        $policy = BrandApprovalPolicy::factory()->forBrand($brand)->create();
        $approval = ApprovalRequest::factory()->forTarget($target, $policy, $actor)->create(['status' => 'APPROVED']);
        $job = app(PublicationIntentRepositoryContract::class)->createHeld($org, $brand, $target, $approval,
            'metric-'.Str::uuid(), now()->subDays(3)->toDateTimeString());
        $post = PublishedPost::factory()->forJobAndAccount($job, $account)->create();

        return [$org, $brand, $actor, $post];
    }

    public function test_one_sync_per_post_per_day_and_fenced_immutable_observation(): void
    {
        [$org,$brand,$actor,$post] = $this->fixture();
        $repo = app(ProviderMetricSyncRepositoryContract::class);
        $run = DB::transaction(fn () => $repo->queue($org, $brand, $post, $actor, '2026-11-15'));
        self::assertSame($run->id, DB::transaction(fn () => $repo->queue($org, $brand, $post, $actor, '2026-11-15'))->id);
        self::assertCount(1, $repo->dueIds(10));
        $owner = bin2hex(random_bytes(24));
        self::assertNotNull($repo->claim($run->id, $owner));
        self::assertNull($repo->claim($run->id, bin2hex(random_bytes(24))));
        $observation = ['video_id' => $post->external_post_id, 'channel_id' => 'UC'.str_repeat('a', 22),
            'views' => 123, 'likes' => null, 'comments' => 5];
        $observation['sha256'] = hash('sha256', json_encode($observation, JSON_THROW_ON_ERROR));
        self::assertFalse($repo->finish($run->id, bin2hex(random_bytes(24)),
            'PROVIDER_API_OBSERVED', $observation));
        self::assertTrue($repo->finish($run->id, $owner, 'PROVIDER_API_OBSERVED', $observation));
        self::assertFalse($repo->finish($run->id, $owner, 'PROVIDER_API_OBSERVED', $observation));
        $report = $repo->report($org, $brand, now()->subHour()->toDateTimeString(), now()->addHour()->toDateTimeString());
        self::assertSame(123, $report['totals']['views']);
        self::assertNull($report['totals']['likes']);
        self::assertSame('PROVIDER_API_OBSERVED', $report['verification']);
        self::assertDatabaseCount('provider_metric_observations', 1);
        $second = Brand::factory()->forOrganisation($org)->create();
        self::assertCount(0, $repo->list($org, $second, 20));
        self::assertSame(0, $repo->report($org, $second, now()->subHour()->toDateTimeString(), now()->addHour()->toDateTimeString())['unique_posts_in_sample']);
    }

    public function test_expired_read_only_lease_can_retry_without_mutating_publication(): void
    {
        [$org,$brand,$actor,$post] = $this->fixture();
        $repo = app(ProviderMetricSyncRepositoryContract::class);
        $run = DB::transaction(fn () => $repo->queue($org, $brand, $post, $actor, '2026-11-15'));
        $owner = bin2hex(random_bytes(24));
        $repo->claim($run->id, $owner);
        $this->travel(3)->minutes();
        self::assertSame(1, $repo->recover(10));
        self::assertFalse($repo->finish($run->id, $owner, 'PROVIDER_API_OBSERVED',
            ['video_id' => $post->external_post_id, 'channel_id' => 'UC'.str_repeat('a', 22),
                'views' => 10, 'likes' => 1, 'comments' => 1, 'sha256' => str_repeat('b', 64)]));
        self::assertSame('RETRY_WAIT',$run->fresh()->state);
        self::assertDatabaseCount('provider_metric_observations',0);
        self::assertSame('PUBLISHED',$post->fresh()->remote_status);
    }
}
