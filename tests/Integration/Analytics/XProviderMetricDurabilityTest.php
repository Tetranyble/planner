<?php

namespace Tests\Integration\Analytics;

use App\Domain\Analytics\Provider\XPostStatistics;
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

/** Execute in disposable MySQL 8; not runnable in source-authoring environment. */
final class XProviderMetricDurabilityTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $version = ContentVersion::factory()->forItem($item)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider('x')->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)
            ->state(['status' => 'ACTIVE', 'external_account_id' => '12345678901234567'])->create();
        $target = ContentTarget::factory()->forVersionAndAccount($version, $account)->create();
        $policy = BrandApprovalPolicy::factory()->forBrand($brand)->create();
        $approval = ApprovalRequest::factory()->forTarget($target, $policy, $actor)->create(['status' => 'APPROVED']);
        $job = app(PublicationIntentRepositoryContract::class)->createHeld($org, $brand, $target, $approval,
            'x-metric-'.Str::uuid(), now()->subDays(2)->toDateTimeString());
        $post = PublishedPost::factory()->forJobAndAccount($job, $account)->create([
            'provider' => 'x', 'external_post_id' => '1923456789012345678', 'remote_status' => 'PUBLISHED']);

        return [$org, $brand, $actor, $post];
    }

    public function test_x_deduplication_identity_fence_and_provider_aggregation(): void
    {
        config()->set('planner.x_metric_sync_enabled', true);
        [$org,$brand,$actor,$post] = $this->fixture();
        $repo = app(ProviderMetricSyncRepositoryContract::class);
        $run = DB::transaction(fn () => $repo->queue($org, $brand, $post, $actor, '2026-11-15'));
        self::assertSame($run->id, DB::transaction(fn () => $repo->queue($org, $brand, $post, $actor, '2026-11-15'))->id);
        self::assertSame('x', $run->provider);
        $lease = bin2hex(random_bytes(24));
        self::assertNotNull($repo->claim($run->id, $lease));
        $wrong = XPostStatistics::normalize(['data' => ['id' => '1923456789012345678', 'author_id' => '999',
            'public_metrics' => ['impression_count' => 40]]], '1923456789012345678', '999');
        self::assertFalse($repo->finish($run->id, $lease, 'PROVIDER_API_OBSERVED', $wrong));
        self::assertDatabaseCount('provider_metric_observations', 0);
        $valid = XPostStatistics::normalize(['data' => ['id' => '1923456789012345678', 'author_id' => '12345678901234567',
            'public_metrics' => ['impression_count' => 100, 'like_count' => 5, 'reply_count' => null]]],
            '1923456789012345678', '12345678901234567');
        self::assertTrue($repo->finish($run->id, $lease, 'PROVIDER_API_OBSERVED', $valid));
        self::assertFalse($repo->finish($run->id, $lease, 'PROVIDER_API_OBSERVED', $valid));
        $data = $repo->report($org, $brand, now()->subHour()->toDateTimeString(), now()->addHour()->toDateTimeString());
        self::assertSame(100, $data['provider_breakdown']['x']['totals']['views']);
        self::assertNull($data['provider_breakdown']['youtube']['totals']);
        self::assertSame('x', $data['single_provider']);
        self::assertNull($data['provider_breakdown']['x']['totals']['comments']);
        self::assertDatabaseCount('provider_metric_observations',1);
        self::assertSame('PUBLISHED',$post->fresh()->remote_status);
    }
}
