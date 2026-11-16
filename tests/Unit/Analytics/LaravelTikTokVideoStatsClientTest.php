<?php

namespace Tests\Unit\Analytics;

use App\Domain\Analytics\Provider\LaravelTikTokVideoStatsClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LaravelTikTokVideoStatsClientTest extends TestCase
{
    public function test_video_query_is_only_read_only_post_endpoint(): void
    {
        Http::preventStrayRequests();
        $id = '7077642457847991554';
        Http::fake(['open.tiktokapis.com/v2/video/query/*' => Http::response([
            'error' => ['code' => 'ok'], 'data' => ['videos' => [['id' => $id, 'view_count' => 10]]],
        ], 200)]);
        $result = (new LaravelTikTokVideoStatsClient)->read('fake-test-token', $id);
        self::assertSame(200, $result['status']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && parse_url($req->url(), PHP_URL_HOST) === 'open.tiktokapis.com'
            && $req->data()['filters']['video_ids'] == [$id]);
        self::assertStringNotContainsString('fake-test-token', json_encode($result));
    }

    public function test_invalid_video_id_rejected_before_transport(): void
    {
        Http::preventStrayRequests();
        $this->expectException(\DomainException::class);
        try {
            (new LaravelTikTokVideoStatsClient)->read('fake-token', 'https://unsafe.test');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_business_error_is_not_recorded_as_success(): void
    {
        Http::preventStrayRequests();
        Http::fake(['open.tiktokapis.com/*' => Http::response(['error' => ['code' => 'scope_not_authorized']], 200)]);
        $result = (new LaravelTikTokVideoStatsClient)->read('fake-token', '7077642457847991554');
        self::assertSame(422, $result['status']);
        self::assertSame([], $result['payload']);
    }
}
