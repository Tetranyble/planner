<?php

namespace Tests\Unit\Analytics;

use App\Domain\Analytics\Provider\LaravelXPostStatsClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LaravelXPostStatsClientTest extends TestCase
{
    public function test_exact_read_only_url_and_bounded_response(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.x.com/2/tweets/*' => Http::response(['data' => ['id' => '12345',
            'author_id' => '123', 'public_metrics' => ['like_count' => 10]]], 200)]);
        $result = (new LaravelXPostStatsClient)->read('synthetic-token', '12345');
        self::assertSame(200, $result['status']);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && parse_url($request->url(), PHP_URL_PATH) === '/2/tweets/12345'
            && str_contains(urldecode($request->url()), 'tweet.fields=author_id,public_metrics'));
    }

    public function test_rejects_invalid_ids_without_any_network_call(): void
    {
        Http::preventStrayRequests();
        try {
            (new LaravelXPostStatsClient)->read('token', 'https://evil.test');
            self::fail('Expected fail');
        } catch (\DomainException) {
        }
        Http::assertNothingSent();
    }
}
