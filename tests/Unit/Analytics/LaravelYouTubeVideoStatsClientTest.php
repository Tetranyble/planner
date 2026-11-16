<?php

namespace Tests\Unit\Analytics;

use App\Domain\Analytics\Provider\LaravelYouTubeVideoStatsClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LaravelYouTubeVideoStatsClientTest extends TestCase
{
    public function test_google_get_is_pinned_and_does_not_expose_the_bearer_token(): void
    {
        $token = 'synthetic-do-not-store';
        Http::preventStrayRequests();
        Http::fake(['www.googleapis.com/youtube/v3/videos*' => Http::response([
            'items' => [['id' => 'Xy123AbC_-1', 'snippet' => ['channelId' => 'UC'.str_repeat('b', 22)],
                'statistics' => ['viewCount' => '9']]],
        ], 200)]);
        $result = (new LaravelYouTubeVideoStatsClient)->read($token, 'Xy123AbC_-1');
        self::assertSame(200, $result['status']);
        self::assertStringNotContainsString($token, json_encode($result));
        Http::assertSent(function ($request) use ($token) {
            $params = parse_url($request->url(), PHP_URL_QUERY);
            parse_str((string) $params, $query);

            return $request->method() === 'GET' && $request->hasHeader('Authorization', 'Bearer '.$token)
                && parse_url($request->url(), PHP_URL_HOST) === 'www.googleapis.com'
                && ($query['id'] ?? null) === 'Xy123AbC_-1'
                && ($query['part'] ?? null) === 'snippet,statistics';
        });
    }

    public function test_invalid_video_id_never_reaches_transport(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        try {
            (new LaravelYouTubeVideoStatsClient)->read('token', 'https://127.0.0.1/');
            self::fail('Invalid ID allowed');
        } catch (\DomainException) {
            Http::assertNothingSent();
        }
    }
}
