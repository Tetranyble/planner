<?php

namespace Tests\Integration\Analytics;

use App\Domain\Analytics\Provider\FacebookPagePostStatistics;
use App\Domain\Analytics\Provider\InstagramMediaStatistics;
use App\Domain\Analytics\Provider\TikTokVideoStatistics;
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

/** Run only against disposable MySQL 8 after Composer/Storage/Kinship are installed. */
final class AdditionalProviderMetricDurabilityTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $provider, string $remotePost, string $accountId): array
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $version = ContentVersion::factory()->forItem($item)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider($provider)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)
            ->state(['status' => 'ACTIVE', 'external_account_id' => $accountId])->create();
        $target = ContentTarget::factory()->forVersionAndAccount($version, $account)->create();
        $policy = BrandApprovalPolicy::factory()->forBrand($brand)->create();
        $approval = ApprovalRequest::factory()->forTarget($target, $policy, $actor)->create(['status' => 'APPROVED']);
        $job = app(PublicationIntentRepositoryContract::class)->createHeld($org, $brand, $target, $approval,
            'metric-evidence-'.Str::uuid(), now()->subDays(2)->toDateTimeString());
        $post = PublishedPost::factory()->forJobAndAccount($job, $account)->create([
            'provider' => $provider, 'external_post_id' => $remotePost, 'remote_status' => 'PUBLISHED']);

        return [$org, $brand, $actor, $post];
    }

    public function test_meta_page_media_view_requires_matching_brand_post_and_page(): void
    {
        config()->set('planner.facebook_metric_sync_enabled', true);
        [$org,$brand,$actor,$post] = $this->fixture('facebook', '123456789_987654321', '123456789');
        $repo = app(ProviderMetricSyncRepositoryContract::class);
        $run = DB::transaction(fn () => $repo->queue($org, $brand, $post, $actor, '2026-11-15'));
        $lease = bin2hex(random_bytes(24));
        self::assertNotNull($repo->claim($run->id, $lease));
        $payload = ['identity' => ['id' => '123456789_987654321', 'from' => ['id' => '123456789']],
            'insights' => ['data' => [['name' => 'post_media_view', 'period' => 'lifetime',
                'values' => [['value' => 30]]]]]];
        $wrong = FacebookPagePostStatistics::normalize($payload, $post->external_post_id, '123456789');
        $wrong['account_id'] = '999999999';
        self::assertFalse($repo->finish($run->id, $lease, 'PROVIDER_API_OBSERVED', $wrong));
        $valid = FacebookPagePostStatistics::normalize($payload, $post->external_post_id, '123456789');
        self::assertTrue($repo->finish($run->id, $lease, 'PROVIDER_API_OBSERVED', $valid));
        self::assertFalse($repo->finish($run->id, $lease, 'PROVIDER_API_OBSERVED', $valid));
        self::assertSame('PUBLISHED', $post->fresh()->remote_status);
        self::assertSame(30, $repo->report($org, $brand, now()->subHour()->toDateTimeString(),
            now()->addHour()->toDateTimeString())['provider_breakdown']['facebook']['totals']['views']);
    }

    public function test_tiktok_identity_bound_to_published_video_and_brand(): void
    {
        config()->set('planner.tiktok_metric_sync_enabled', true);
        [$org,$brand,$actor,$post] = $this->fixture('tiktok', '7077642457847991554', 'creator-open-id');
        $repo = app(ProviderMetricSyncRepositoryContract::class);
        $run = DB::transaction(fn () => $repo->queue($org, $brand, $post, $actor, '2026-11-15'));
        $lease = bin2hex(random_bytes(24));
        self::assertNotNull($repo->claim($run->id, $lease));
        $valid = TikTokVideoStatistics::normalize(['error' => ['code' => 'ok'], 'data' => ['videos' => [
            ['id' => '7077642457847991554', 'view_count' => 150, 'like_count' => 7, 'comment_count' => 2],
        ]]], $post->external_post_id, 'creator-open-id');
        self::assertTrue($repo->finish($run->id, $lease, 'PROVIDER_API_OBSERVED', $valid));
        self::assertNull($repo->report($org, $brand, now()->subHour()->toDateTimeString(),
            now()->addHour()->toDateTimeString())['provider_breakdown']['youtube']['totals']);
    }

    public function test_instagram_media_owner_and_video_count_are_immutable_evidence(): void
    {
        config()->set('planner.instagram_metric_sync_enabled', true);
        [$org,$brand,$actor,$post] = $this->fixture('instagram', '17918920912340654', '17841405309211844');
        $repo = app(ProviderMetricSyncRepositoryContract::class);
        $run = DB::transaction(fn () => $repo->queue($org, $brand, $post, $actor, '2026-11-15'));
        $lease = bin2hex(random_bytes(24));
        self::assertNotNull($repo->claim($run->id, $lease));
        $valid = InstagramMediaStatistics::normalize(['identity' => ['id' => $post->external_post_id,
            'owner' => ['id' => '17841405309211844'], 'like_count' => 4],
            'insights' => ['data' => [['name' => 'views', 'total_value' => ['value' => 200]]]]],
            $post->external_post_id, '17841405309211844');
        self::assertTrue($repo->finish($run->id, $lease, 'PROVIDER_API_OBSERVED', $valid));
        $report = $repo->report($org, $brand, now()->subHour()->toDateTimeString(),
            now()->addHour()->toDateTimeString());
        self::assertSame(200, $report['provider_breakdown']['instagram']['totals']['views']);
        self::assertNull($report['provider_breakdown']['instagram']['totals']['comments']);
    }
}
