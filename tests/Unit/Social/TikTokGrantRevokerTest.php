<?php

namespace Tests\Unit\Social;

use App\Social\Providers\TikTokGrantRevoker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TikTokGrantRevokerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('social_integrations.providers.tiktok', [
            'client_id' => 'test-client-key', 'client_secret' => 'test-client-secret',
        ]);
    }

    public function test_verified_fixed_host_request_and_empty_acknowledgement(): void
    {
        Http::fake(['*open.tiktokapis.com/v2/oauth/revoke/*' => Http::response([], 200)]);
        Http::preventStrayRequests();
        app(TikTokGrantRevoker::class)->revoke('synthetic-sensitive-access-token');
        Http::assertSent(fn ($request) => $request->url() === 'https://open.tiktokapis.com/v2/oauth/revoke/'
            && $request->method() === 'POST'
            && $request['client_key'] === 'test-client-key'
            && $request['token'] === 'synthetic-sensitive-access-token');
    }

    public function test_provider_rejection_does_not_count_as_acknowledgement(): void
    {
        Http::fake(['*open.tiktokapis.com/v2/oauth/revoke/*' => Http::response(['error' => 'invalid_request'], 400)]);
        $this->expectException(\RuntimeException::class);
        app(TikTokGrantRevoker::class)->revoke('bad-test-token');
    }
}
