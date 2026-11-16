<?php

namespace Tests\Integration\Analytics;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\SocialMetricRepositoryContract;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Must run against disposable MySQL 8; not verified in this environment. */
final class SocialMetricPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function record(string $post, string $key, int $impressions = 100): array
    {
        return ['provider' => 'instagram', 'account_reference' => 'synthetic-account',
            'external_post_reference' => $post, 'observed_at' => '2026-11-14 12:00:00',
            'impressions' => $impressions, 'reach' => 50, 'likes' => 10, 'comments' => 2,
            'shares' => 1, 'clicks' => 3, 'source_type' => 'MANUAL_EXPORT',
            'source_reference' => 'sample-export-reference', 'submission_key' => $key,
            'payload_hash' => hash('sha256', $post.$key.$impressions)];
    }

    public function test_brand_scoping_latest_per_post_and_unique_submission(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $second = Brand::factory()->forOrganisation($org)->create();
        $user = User::factory()->create();
        $repo = app(SocialMetricRepositoryContract::class);
        $first = $repo->store($org, $brand, $user->id, $this->record('post-1', str_repeat('a', 64)));
        self::assertSame($first->id, $repo->findSubmission($org, $brand, str_repeat('a', 64))->id);
        self::assertNull($repo->findSubmission($org, $second, str_repeat('a', 64)));
        $later = $this->record('post-1', str_repeat('b', 64), 225);
        $later['observed_at'] = '2026-11-14 13:00:00';
        $repo->store($org, $brand, $user->id, $later);
        $repo->store($org, $second, $user->id, $this->record('post-2', str_repeat('c', 64), 999));
        $report = $repo->report($org, $brand, '2026-11-14 00:00:00', '2026-11-15 00:00:00');
        self::assertSame(1, $report['unique_posts_in_sample']);
        self::assertSame(225, $report['totals']['impressions']);
        self::assertSame('USER_REPORTED_UNVERIFIED', $report['verification']);
        self::assertSame(1, $repo->report($org, $second, '2026-11-14 00:00:00', '2026-11-15 00:00:00')['unique_posts_in_sample']);
        $this->expectException(QueryException::class);
        $repo->store($org,$brand,$user->id,$this->record('post-new',str_repeat('a',64)));
    }
}
