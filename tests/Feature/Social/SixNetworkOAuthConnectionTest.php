<?php

namespace Tests\Feature\Social;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\SocialAccount;
use App\Models\SocialOAuthSelection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Integration contract: fake provider I/O; use real MySQL, factories and Kinship route middleware. */
class SixNetworkOAuthConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['facebook', 'instagram', 'linkedin', 'x'] as $provider) {
            config()->set("social_integrations.providers.{$provider}", [
                'client_id' => "fixture-{$provider}-id", 'client_secret' => "fixture-{$provider}-secret",
            ]);
        }
        config()->set('social_integrations.linkedin_organizations_enabled', false);
    }

    private function provision(string $name): array
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => $name])->assertCreated();
        $organisation = Organisation::query()->where('name', $name)->firstOrFail();
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$organisation->uuid}/brands", [
            'name' => $name.' Brand',
        ])->assertCreated();
        $brand = Brand::query()->where('organisation_id', $organisation->id)->firstOrFail();

        return [$owner, $organisation, $brand];
    }

    private function start(string $provider, User $user, Organisation $org, Brand $brand): string
    {
        $response = $this->actingAs($user)->post("/organisations/{$org->uuid}/brands/{$brand->uuid}/social-connections/{$provider}/start");
        $response->assertRedirect();
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $params);

        return $params['state'];
    }

    public function test_facebook_page_connects_without_a_refresh_token(): void
    {
        [$user,$org,$brand] = $this->provision('Multi Social Facebook');
        $state = $this->start('facebook', $user, $org, $brand);
        Http::fake([
            '*graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'fb-fixture-secret', 'expires_in' => 3600]),
            '*graph.facebook.com/*/me?fields=id*' => Http::response(['id' => 'fb-user-1']),
            '*graph.facebook.com/*/me/accounts*' => Http::response(['data' => [['id' => 'fb-page-1', 'name' => 'Pensuh']]]),
            '*graph.facebook.com/*/me/permissions*' => Http::response(['data' => [
                ['permission' => 'pages_show_list', 'status' => 'granted'], ['permission' => 'pages_read_engagement', 'status' => 'granted'],
            ]]),
        ]);
        Http::preventStrayRequests();
        $this->actingAs($user)->get("/integrations/social/facebook/callback?state={$state}&code=dummy")->assertRedirect();
        $connection = ProviderConnection::query()->where('organisation_id', $org->id)->where('provider', 'facebook')->firstOrFail();
        $secret = ProviderConnectionCredential::query()->where('provider_connection_id', $connection->id)->firstOrFail();
        $this->assertSame('fb-fixture-secret', Crypt::decryptString($secret->access_token_ciphertext));
        $this->assertNull($secret->refresh_token_ciphertext);
        $this->assertNull($connection->fresh()->refresh_token_secret_ref);
        $this->assertDatabaseHas('social_accounts', ['brand_id' => $brand->id, 'provider' => 'facebook', 'external_account_id' => 'fb-page-1']);
        $this->assertDatabaseHas('social_account_capabilities', ['brand_id' => $brand->id, 'capability' => 'post.publish', 'mode' => 'UNAVAILABLE']);
    }

    public function test_multiple_facebook_pages_require_explicit_selection_and_consumption(): void
    {
        [$user,$org,$brand] = $this->provision('Multi Social Selection');
        $state = $this->start('facebook', $user, $org, $brand);
        Http::fake([
            '*graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'multi-secret-token', 'expires_in' => 3600]),
            '*graph.facebook.com/*/me?fields=id*' => Http::response(['id' => 'meta-user-selection']),
            '*graph.facebook.com/*/me/permissions*' => Http::response(['data' => [
                ['permission' => 'pages_show_list', 'status' => 'granted'], ['permission' => 'pages_read_engagement', 'status' => 'granted'],
            ]]),
            '*graph.facebook.com/*/me/accounts*' => Http::response(['data' => [
                ['id' => 'page-one', 'name' => 'Pensuh'], ['id' => 'page-two', 'name' => 'FlymyMall'],
            ]]),
        ]);
        Http::preventStrayRequests();
        $this->actingAs($user)->get("/integrations/social/facebook/callback?state={$state}&code=dummy")->assertRedirect();
        $pending = SocialOAuthSelection::query()->where('brand_id', $brand->id)->firstOrFail();
        $this->assertSame(0, SocialAccount::query()->where('brand_id', $brand->id)->count());
        $this->assertStringNotContainsString('multi-secret-token', $pending->grant_ciphertext);
        $url = "/organisations/{$org->uuid}/brands/{$brand->uuid}/social-selections/{$pending->uuid}";
        $body = $this->actingAs($user)->get($url)->assertOk()->getContent();
        $this->assertStringNotContainsString('multi-secret-token', $body);
        $this->actingAs($user)->post($url.'/confirm', ['account_ids' => ['page-two']])->assertRedirect();
        $this->assertDatabaseHas('social_accounts', ['brand_id' => $brand->id, 'provider' => 'facebook', 'external_account_id' => 'page-two']);
        $this->assertSame(1, SocialAccount::query()->where('brand_id', $brand->id)->count());
        $this->assertNotNull($pending->fresh()->consumed_at);
        $this->actingAs($user)->post($url.'/confirm', ['account_ids' => ['page-one']])->assertStatus(410);
    }

    public function test_account_selection_rejects_unknown_ids_and_expired_grants(): void
    {
        [$user,$org,$brand] = $this->provision('Multi Social Selection Guard');
        $pending = SocialOAuthSelection::factory()->forBrandAndActor($brand, $user)->create();
        $url = "/organisations/{$org->uuid}/brands/{$brand->uuid}/social-selections/{$pending->uuid}";
        $this->actingAs($user)->post($url.'/confirm', ['account_ids' => ['not-consented']])
            ->assertSessionHasErrors('account_ids');
        $this->assertNull($pending->fresh()->consumed_at);
        $pending->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->actingAs($user)->get($url)->assertStatus(410);
    }

    public function test_instagram_discovers_only_linked_professional_accounts(): void
    {
        [$user,$org,$brand] = $this->provision('Multi Social Instagram');
        $state = $this->start('instagram', $user, $org, $brand);
        Http::fake([
            '*graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'ig-fixture-secret', 'expires_in' => 3600]),
            '*graph.facebook.com/*/me?fields=id*' => Http::response(['id' => 'meta-user']),
            '*graph.facebook.com/*/me/permissions*' => Http::response(['data' => [
                ['permission' => 'pages_show_list', 'status' => 'granted'], ['permission' => 'pages_read_engagement', 'status' => 'granted'],
                ['permission' => 'instagram_basic', 'status' => 'granted'],
            ]]),
            '*graph.facebook.com/*/me/accounts*' => Http::response(['data' => [
                ['id' => 'page-1', 'name' => 'Pensuh', 'instagram_business_account' => ['id' => 'ig-account-1', 'username' => 'pensuhh']],
                ['id' => 'page-2', 'name' => 'No Instagram'],
            ]]),
        ]);
        Http::preventStrayRequests();
        $this->actingAs($user)->get("/integrations/social/instagram/callback?state={$state}&code=dummy")->assertRedirect();
        $this->assertDatabaseHas('social_accounts', ['brand_id' => $brand->id, 'provider' => 'instagram', 'external_account_id' => 'ig-account-1']);
        $this->assertSame(1, SocialAccount::query()->where('brand_id', $brand->id)->count());
    }

    public function test_linkedin_member_connection_does_not_claim_company_page_access(): void
    {
        [$user,$org,$brand] = $this->provision('Multi Social LinkedIn');
        $state = $this->start('linkedin', $user, $org, $brand);
        Http::fake([
            '*www.linkedin.com/oauth/v2/accessToken*' => Http::response(['access_token' => 'li-fixture-secret', 'expires_in' => 3600]),
            '*api.linkedin.com/v2/userinfo*' => Http::response(['sub' => 'member-id-1', 'name' => 'Example Member']),
        ]);
        Http::preventStrayRequests();
        $this->actingAs($user)->get("/integrations/social/linkedin/callback?state={$state}&code=dummy")->assertRedirect();
        $this->assertDatabaseHas('social_accounts', ['brand_id' => $brand->id, 'provider' => 'linkedin', 'account_kind' => 'MEMBER']);
    }

    public function test_x_oauth_uses_pkce_verifier_and_returns_connected_profile(): void
    {
        [$user,$org,$brand] = $this->provision('Multi Social X');
        $state = $this->start('x', $user, $org, $brand);
        Http::fake([
            '*api.x.com/2/oauth2/token*' => Http::response([
                'access_token' => 'x-fixture-secret', 'refresh_token' => 'x-refresh-fixture',
                'expires_in' => 7200, 'scope' => 'tweet.read users.read offline.access',
            ]),
            '*api.x.com/2/users/me*' => Http::response(['data' => ['id' => 'x-member-1', 'username' => 'pensuhh', 'name' => 'Pensuh']]),
        ]);
        Http::preventStrayRequests();
        $this->actingAs($user)->get("/integrations/social/x/callback?state={$state}&code=dummy")->assertRedirect();
        Http::assertSent(fn ($request) => str_contains($request->url(), '/oauth2/token')
            && is_string($request['code_verifier'] ?? null) && strlen($request['code_verifier']) >= 43);
        $this->assertDatabaseHas('social_accounts', ['brand_id' => $brand->id, 'provider' => 'x', 'external_account_id' => 'x-member-1']);
    }
}
