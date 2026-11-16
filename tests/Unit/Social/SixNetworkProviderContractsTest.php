<?php

namespace Tests\Unit\Social;

use App\Social\Data\DiscoveredSocialAccount;
use App\Social\Data\OAuthGrant;
use App\Social\Providers\FacebookOAuthProvider;
use App\Social\Providers\InstagramOAuthProvider;
use App\Social\Providers\LinkedInOAuthProvider;
use App\Social\Providers\MetaOAuthClient;
use App\Social\Providers\XOAuthProvider;
use App\Social\SocialProviderRegistry;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

final class SixNetworkProviderContractsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('social_integrations.providers.facebook', ['client_id' => 'meta-client', 'client_secret' => 'meta-secret']);
        config()->set('social_integrations.providers.instagram', ['client_id' => 'meta-client', 'client_secret' => 'meta-secret']);
        config()->set('social_integrations.providers.linkedin', ['client_id' => 'li-client', 'client_secret' => 'li-secret']);
        config()->set('social_integrations.providers.x', ['client_id' => 'x-client', 'client_secret' => 'x-secret']);
        config()->set('social_integrations.linkedin_organizations_enabled', false);
    }

    public function test_all_six_providers_are_registered_and_unconfigured_providers_fail_closed(): void
    {
        $this->assertSame(['facebook', 'instagram', 'linkedin', 'x', 'tiktok', 'youtube'], SocialProviderRegistry::PROVIDERS);
        $registry = app(SocialProviderRegistry::class);
        $this->assertTrue($registry->isConfigured('facebook'));
        $this->assertTrue($registry->isConfigured('instagram'));
        $this->assertTrue($registry->isConfigured('linkedin'));
        $this->assertTrue($registry->isConfigured('x'));
        $this->assertSame('x', $registry->resolve('x')->key());
        $this->assertFalse($registry->isConfigured('untrusted-provider'));
    }

    public function test_facebook_and_instagram_request_distinct_scopes_and_fixed_callback(): void
    {
        $meta = app(MetaOAuthClient::class);
        foreach ([new FacebookOAuthProvider($meta), new InstagramOAuthProvider($meta)] as $provider) {
            $redirect = 'https://planner.example/integrations/social/'.$provider->key().'/callback';
            parse_str((string) parse_url($provider->authorizationUrl(str_repeat('a', 64), $redirect), PHP_URL_QUERY), $query);
            $this->assertSame($redirect, $query['redirect_uri']);
            $this->assertSame(str_repeat('a', 64), $query['state']);
            $this->assertContains('pages_show_list', explode(',', $query['scope']));
            $this->assertNotContains('pages_manage_posts', explode(',', $query['scope']));
            $this->assertSame($provider->key() === 'instagram', in_array('instagram_basic', explode(',', $query['scope'])));
        }
    }

    public function test_meta_pages_and_instagram_accounts_are_discovered_separately(): void
    {
        Http::fake(['*graph.facebook.com/*/me/accounts*' => Http::response([
            'data' => [
                ['id' => 'page-1', 'name' => 'Pensuh', 'instagram_business_account' => ['id' => 'ig-1', 'username' => 'pensuh', 'name' => 'Pensuh']],
                ['id' => 'page-2', 'name' => 'FlymyMall'],
            ],
        ])]);
        Http::preventStrayRequests();
        $grant = new OAuthGrant('access', null, 'meta-user', ['pages_show_list'], 3600);
        $meta = app(MetaOAuthClient::class);
        $fb = (new FacebookOAuthProvider($meta))->discover($grant);
        $ig = (new InstagramOAuthProvider($meta))->discover($grant);
        $this->assertCount(2, $fb);
        $this->assertCount(1, $ig);
        $this->assertSame('page-1', $fb[0]->externalId);
        $this->assertSame('ig-1', $ig[0]->externalId);
        $this->assertSame('pensuh', $ig[0]->handle);
        $this->assertSame('UNAVAILABLE', (new FacebookOAuthProvider($meta))->capabilities($grant, $fb[0])['post.publish']['mode']);
        $this->assertSame('UNAVAILABLE', (new InstagramOAuthProvider($meta))->capabilities($grant, $ig[0])['media.publish']['mode']);
    }

    public function test_meta_denied_permission_is_not_treated_as_granted(): void
    {
        Http::fake([
            '*graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'access', 'expires_in' => 3600]),
            '*graph.facebook.com/*/me?fields=id*' => Http::response(['id' => 'meta-user']),
            '*graph.facebook.com/*/me/permissions*' => Http::response(['data' => [
                ['permission' => 'pages_show_list', 'status' => 'granted'],
                ['permission' => 'pages_read_engagement', 'status' => 'declined'],
            ]]),
        ]);
        Http::preventStrayRequests();
        $this->expectException(RuntimeException::class);
        (new FacebookOAuthProvider(app(MetaOAuthClient::class)))
            ->exchange('code', 'https://planner.example/integrations/social/facebook/callback');
    }

    public function test_linkedin_company_page_discovery_requires_approved_configuration(): void
    {
        config()->set('social_integrations.linkedin_organizations_enabled', true);
        Http::fake([
            '*api.linkedin.com/v2/userinfo*' => Http::response(['sub' => 'member-123', 'name' => 'Admin Member']),
            '*api.linkedin.com/rest/organizationAcls*' => Http::response(['elements' => [
                ['role' => 'ADMINISTRATOR', 'organization' => 'urn:li:organization:66777'],
            ]]),
            '*api.linkedin.com/rest/organizations/66777*' => Http::response([
                'localizedName' => 'Pensuh Ltd', 'vanityName' => 'pensuh',
            ]),
        ]);
        Http::preventStrayRequests();
        $accounts = (new LinkedInOAuthProvider)->discover(new OAuthGrant('access', null, 'member-123', ['rw_organization_admin'], 3600));
        $this->assertCount(2, $accounts);
        $this->assertSame('66777', $accounts[1]->externalId);
        $this->assertSame('ORGANIZATION', $accounts[1]->kind);
    }

    public function test_instagram_rejects_ineligible_accounts_without_professional_link(): void
    {
        Http::fake(['*graph.facebook.com/*/me/accounts*' => Http::response(['data' => [['id' => 'page-3', 'name' => 'Not linked']]])]);
        $this->expectException(ValidationException::class);
        (new InstagramOAuthProvider(app(MetaOAuthClient::class)))->discover(new OAuthGrant('access', null, 'user', [], 3600));
    }

    public function test_linkedin_member_discovery_rejects_identity_mismatch(): void
    {
        Http::fake(['*api.linkedin.com/v2/userinfo*' => Http::response(['sub' => 'another-member', 'name' => 'Example'])]);
        $this->expectException(RuntimeException::class);
        (new LinkedInOAuthProvider)->discover(new OAuthGrant('access', null, 'expected-member', [], 3600));
    }

    public function test_linkedin_organization_scope_is_opt_in_and_never_implied_by_oidc(): void
    {
        $provider = new LinkedInOAuthProvider;
        parse_str((string) parse_url($provider->authorizationUrl(str_repeat('a', 64), 'https://planner.example/oauth'), PHP_URL_QUERY), $query);
        $this->assertSame('openid profile email', $query['scope']);
        config()->set('social_integrations.linkedin_organizations_enabled', true);
        parse_str((string) parse_url($provider->authorizationUrl(str_repeat('a', 64), 'https://planner.example/oauth'), PHP_URL_QUERY), $query);
        $this->assertContains('rw_organization_admin', explode(' ', $query['scope']));
    }

    public function test_x_uses_s256_pkce_and_does_not_request_post_write_permission(): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $provider = new XOAuthProvider;
        $state = str_repeat('z', 64);
        parse_str((string) parse_url($provider->authorizationUrl($state, 'https://planner.example/x/callback'), PHP_URL_QUERY), $query);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame($state, $query['state']);
        $this->assertSame(43, strlen($query['code_challenge']));
        $this->assertContains('offline.access', explode(' ', $query['scope']));
        $this->assertNotContains('tweet.write', explode(' ', $query['scope']));
        $this->assertSame('UNAVAILABLE', $provider->capabilities(new OAuthGrant('a', 'r', '1', [], 3600), new DiscoveredSocialAccount('1', 'CREATOR', 'X'))['post.publish']['mode']);
    }

    public function test_x_exchange_requires_pkce_state(): void
    {
        $this->expectException(RuntimeException::class);
        (new XOAuthProvider)->exchange('code', 'https://planner.example/x/callback');
    }
}
