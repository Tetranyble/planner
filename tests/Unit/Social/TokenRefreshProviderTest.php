<?php

namespace Tests\Unit\Social;

use App\Social\Contracts\RefreshableSocialProvider;
use App\Social\Exceptions\TokenRefreshFailure;
use App\Social\Providers\FacebookOAuthProvider;
use App\Social\Providers\InstagramOAuthProvider;
use App\Social\Providers\LinkedInOAuthProvider;
use App\Social\Providers\MetaOAuthClient;
use App\Social\Providers\TikTokOAuthProvider;
use App\Social\Providers\XOAuthProvider;
use App\Social\Providers\YouTubeOAuthProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TokenRefreshProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['youtube', 'tiktok', 'x', 'linkedin'] as $provider) {
            config()->set("social_integrations.providers.{$provider}.client_id", "test-{$provider}-id");
            config()->set("social_integrations.providers.{$provider}.client_secret", "test-{$provider}-secret");
        }
    }

    public function test_provider_refresh_support_is_explicit(): void
    {
        foreach ([new TikTokOAuthProvider, new YouTubeOAuthProvider, new XOAuthProvider, new LinkedInOAuthProvider] as $adapter) {
            $this->assertInstanceOf(RefreshableSocialProvider::class, $adapter);
        }
        $this->assertNotInstanceOf(RefreshableSocialProvider::class, new FacebookOAuthProvider(new MetaOAuthClient));
        $this->assertNotInstanceOf(RefreshableSocialProvider::class, new InstagramOAuthProvider(new MetaOAuthClient));
    }

    public function test_tiktok_refresh_persists_required_rotation_data_and_does_not_log_secrets(): void
    {
        Http::fake(['*open.tiktokapis.com/*' => Http::response([
            'access_token' => 'fresh-secret', 'refresh_token' => 'rotated-secret',
            'expires_in' => 7200, 'refresh_expires_in' => 180000,
            'scope' => 'user.info.basic', 'open_id' => 'fixture-open-id', 'error' => ['code' => 'ok'],
        ])]);
        Http::preventStrayRequests();
        $result = (new TikTokOAuthProvider)->refresh('synthetic-old-token');
        $this->assertSame('fresh-secret', $result->accessToken);
        $this->assertSame('rotated-secret', $result->refreshToken);
        $this->assertSame(7200, $result->expiresIn);
        Http::assertSent(fn ($request) => $request->url() === 'https://open.tiktokapis.com/v2/oauth/token/'
            && $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'synthetic-old-token');
    }

    public function test_google_and_linkedin_do_not_require_new_refresh_token(): void
    {
        Http::fake([
            '*oauth2.googleapis.com/token' => Http::response(['access_token' => 'next-google', 'expires_in' => 3600]),
            '*www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'next-linkedin', 'expires_in' => 3600]),
        ]);
        Http::preventStrayRequests();
        $this->assertNull((new YouTubeOAuthProvider)->refresh('old-google')->refreshToken);
        $this->assertNull((new LinkedInOAuthProvider)->refresh('old-linkedin')->refreshToken);
    }

    public function test_rotating_provider_rejects_missing_new_refresh_token(): void
    {
        Http::fake(['*api.x.com/*' => Http::response(['access_token' => 'a', 'expires_in' => 3600])]);
        Http::preventStrayRequests();
        $this->expectException(TokenRefreshFailure::class);
        (new XOAuthProvider)->refresh('old-x');
    }

    public function test_invalid_grant_fails_closed_with_sanitized_error_code(): void
    {
        Http::fake(['*oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'sensitive secret'], 400)]);
        try {
            (new YouTubeOAuthProvider)->refresh('old-google');
            $this->fail('Expected invalid grant.');
        } catch (TokenRefreshFailure $failure) {
            $this->assertSame('INVALID_GRANT', $failure->safeCode);
            $this->assertStringNotContainsString('sensitive', $failure->getMessage());
        }
    }

    public function test_known_rate_limit_can_be_deferred_without_replaying_unknown_outcome(): void
    {
        Http::fake(['*oauth2.googleapis.com/*' => Http::response(['error' => 'rate_limit'], 429)]);
        try {
            (new YouTubeOAuthProvider)->refresh('old-google');
            $this->fail('Expected rate limit.');
        } catch (TokenRefreshFailure $failure) {
            $this->assertTrue($failure->retrySafe);
            $this->assertSame('RATE_LIMIT', $failure->safeCode);
        }
    }
}
