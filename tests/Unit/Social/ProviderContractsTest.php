<?php

namespace Tests\Unit\Social;

use App\Social\Data\DiscoveredSocialAccount;
use App\Social\Data\OAuthGrant;
use App\Social\Providers\TikTokOAuthProvider;
use App\Social\Providers\YouTubeOAuthProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class ProviderContractsTest extends TestCase
{
    public function test_tiktok_redirect_uses_static_uri_exact_state_and_minimal_scope(): void
    {
        config()->set('social_integrations.providers.tiktok.client_id', 'demo-key');
        $url = (new TikTokOAuthProvider)->authorizationUrl(str_repeat('a', 64), 'https://planner.example/integrations/social/tiktok/callback');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        $this->assertSame('user.info.basic', $params['scope']);
        $this->assertSame(str_repeat('a', 64), $params['state']);
        $this->assertSame('https://planner.example/integrations/social/tiktok/callback', $params['redirect_uri']);
    }

    public function test_tiktok_discovery_rejects_foreign_identity(): void
    {
        Http::fake(['*open.tiktokapis.com/v2/user/info/*' => Http::response([
            'data' => ['user' => ['open_id' => 'unexpected-id']], 'error' => ['code' => 'ok'],
        ])]);
        $this->expectException(RuntimeException::class);
        (new TikTokOAuthProvider)->discover(new OAuthGrant('token', 'refresh', 'expected-id', ['user.info.basic'], 7200));
    }

    public function test_youtube_publishing_is_not_automatically_enabled_by_read_scope(): void
    {
        $grant = new OAuthGrant('access', 'refresh', '', ['https://www.googleapis.com/auth/youtube.readonly'], 3600);
        $mode = (new YouTubeOAuthProvider)->capabilities($grant,
            new DiscoveredSocialAccount('UCexample', 'CHANNEL', 'Example'));
        $this->assertSame('UNAVAILABLE', $mode['video.publish']['mode']);
        $this->assertSame('READ_ONLY',$mode['profile.read']['mode']);
    }
}
