<?php

namespace Tests\Unit\Analytics;

use App\Domain\Analytics\Provider\LaravelInstagramMediaStatsClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LaravelInstagramMediaStatsClientTest extends TestCase
{
    public function test_exact_media_owner_is_checked_before_views_are_read(): void
    {
        Http::preventStrayRequests();
        config()->set('social_integrations.meta_graph_version', 'v27.0');
        $media = '17895695668004550';
        $owner = '17841400123456789';
        Http::fake([
            'graph.facebook.com/v27.0/'.$media.'/insights*' => Http::response(['data' => [
                ['name' => 'views', 'total_value' => ['value' => 27]],
            ]], 200),
            'graph.facebook.com/v27.0/'.$media.'*' => Http::response([
                'id' => $media, 'owner' => ['id' => $owner], 'like_count' => 5, 'comments_count' => 1,
            ], 200),
        ]);
        $result = (new LaravelInstagramMediaStatsClient)->read('synthetic-access-token', $media, $owner);
        self::assertSame(200, $result['status']);
        self::assertSame(27, $result['payload']['insights']['data'][0]['total_value']['value']);
        self::assertStringNotContainsString('synthetic-access-token', json_encode($result));
        Http::assertSentCount(2);
        Http::assertSent(fn ($req) => $req->method() === 'GET'
            && str_ends_with(parse_url($req->url(), PHP_URL_PATH), '/insights'));
    }

    public function test_cross_account_media_rejects_before_reading_insights(): void
    {
        Http::preventStrayRequests();
        Http::fake(['graph.facebook.com/*' => Http::response([
            'id' => '17895695668004550', 'owner' => ['id' => '17841400999999999'],
        ], 200)]);
        $result = (new LaravelInstagramMediaStatsClient)->read('fake', '17895695668004550', '17841400123456789');
        self::assertSame(422, $result['status']);
        Http::assertSentCount(1);
    }

    public function test_invalid_identity_refuses_network_call(): void
    {
        Http::preventStrayRequests();
        $this->expectException(\DomainException::class);
        try {
            (new LaravelInstagramMediaStatsClient)->read('fake', 'https://unknown.invalid', '17841400123456789');
        } finally {
            Http::assertNothingSent();
        }
    }
}
