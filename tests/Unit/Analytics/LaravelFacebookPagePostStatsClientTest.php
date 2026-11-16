<?php

namespace Tests\Unit\Analytics;

use App\Domain\Analytics\Provider\LaravelFacebookPagePostStatsClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LaravelFacebookPagePostStatsClientTest extends TestCase
{
    public function test_read_only_identity_then_insights_and_no_token_in_result(): void
    {
        Http::preventStrayRequests();
        config()->set('social_integrations.meta_graph_version', 'v27.0');
        $page = '123456789';
        $post = $page.'_987654321';
        Http::fake([
            'graph.facebook.com/v27.0/'.$post.'/insights*' => Http::response(['data' => [
                ['name' => 'post_media_view', 'period' => 'lifetime', 'values' => [['value' => 12]]],
            ]], 200),
            'graph.facebook.com/v27.0/'.$post.'*' => Http::response(['id' => $post, 'from' => ['id' => $page]], 200),
        ]);
        $result = (new LaravelFacebookPagePostStatsClient)->read('synthetic-page-token', $post, $page);
        self::assertSame(200, $result['status']);
        self::assertSame(12, $result['payload']['insights']['data'][0]['values'][0]['value']);
        self::assertStringNotContainsString('synthetic-page-token', json_encode($result));
        Http::assertSentCount(2);
        Http::assertSent(fn ($req) => $req->method() === 'GET' && str_ends_with(parse_url($req->url(), PHP_URL_PATH), '/insights'));
    }

    public function test_wrong_identity_never_requests_insights(): void
    {
        Http::preventStrayRequests();
        config()->set('social_integrations.meta_graph_version', 'v27.0');
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => '123456789_987654321', 'from' => ['id' => '999999999']], 200)]);
        $result = (new LaravelFacebookPagePostStatsClient)->read('synthetic-token', '123456789_987654321', '123456789');
        self::assertSame(422, $result['status']);
        Http::assertSentCount(1);
    }

    public function test_foreign_ids_block_before_any_network_call(): void
    {
        Http::preventStrayRequests();
        $this->expectException(\DomainException::class);
        try {
            (new LaravelFacebookPagePostStatsClient)->read('fake', '888_12345', '123456789');
        } finally {
            Http::assertNothingSent();
        }
    }
}
