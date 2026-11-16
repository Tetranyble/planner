<?php

namespace Tests\Feature\Social;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\SocialAccount;
use App\Models\SocialOauthAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** MySQL + Kinship + Http::fake contract tests; providers never receive real credentials. */
final class SocialOAuthConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('social_integrations.providers.tiktok', [
            'client_id' => 'test-tiktok-client', 'client_secret' => 'test-tiktok-secret',
        ]);
        config()->set('social_integrations.providers.youtube', [
            'client_id' => 'test-google-client', 'client_secret' => 'test-google-secret',
        ]);

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function ownerAndBrand(string $name): array
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

    private function routeFor(Organisation $organisation, Brand $brand): string
    {
        return "/organisations/{$organisation->uuid}/brands/{$brand->uuid}";
    }

    private function start(User $actor, Organisation $org, Brand $brand, string $provider = 'tiktok'): string
    {
        $response = $this->actingAs($actor)->post($this->routeFor($org, $brand)."/social-connections/{$provider}/start");
        $response->assertRedirect();
        $url = (string) $response->headers->get('Location');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(64, strlen((string) ($query['state'] ?? '')));

        return $query['state'];
    }

    private function fakeTikTok(string $openId = 'test-open-41'): void
    {
        Http::fake([
            '*open.tiktokapis.com/v2/oauth/token/*' => Http::response([
                'access_token' => 'fictional-access-secret-abc',
                'refresh_token' => 'fictional-refresh-secret-abc',
                'open_id' => $openId, 'expires_in' => 7200,
                'refresh_expires_in' => 15552000, 'scope' => 'user.info.basic',
                'token_type' => 'Bearer',
            ], 200),
            '*open.tiktokapis.com/v2/user/info/*' => Http::response([
                'data' => ['user' => ['open_id' => $openId, 'display_name' => 'Sample creator']],
                'error' => ['code' => 'ok', 'message' => ''],
            ], 200),
        ]);
        Http::preventStrayRequests();
    }

    public function test_tiktok_oauth_connects_and_never_exposes_plaintext_credentials(): void
    {
        [$owner,$org,$brand] = $this->ownerAndBrand('Social Tiktok A');
        $state = $this->start($owner, $org, $brand);
        $attempt = SocialOauthAttempt::query()->firstOrFail();
        $this->assertSame(hash('sha256', $state), $attempt->state_digest);
        $this->assertNotSame($state, $attempt->state_digest);
        $this->fakeTikTok();
        $this->actingAs($owner)->get("/integrations/social/tiktok/callback?state={$state}&code=dummy-code")
            ->assertRedirect($this->routeFor($org, $brand).'/social-accounts');
        $connection = ProviderConnection::query()->where('organisation_id', $org->id)->firstOrFail();
        $this->assertSame('CONNECTED', $connection->status);
        $credential = ProviderConnectionCredential::query()->where('provider_connection_id', $connection->id)->firstOrFail();
        $this->assertStringNotContainsString('fictional-access-secret-abc', $credential->access_token_ciphertext);
        $this->assertSame('fictional-access-secret-abc', Crypt::decryptString($credential->access_token_ciphertext));
        $account = SocialAccount::query()->where('brand_id', $brand->id)->firstOrFail();
        $this->assertSame('test-open-41', $account->external_account_id);
        $this->assertDatabaseHas('social_account_capabilities', [
            'social_account_id' => $account->id, 'capability' => 'video.publish', 'mode' => 'ASSISTED',
        ]);
        $body = $this->actingAs($owner)->getJson("/api/v1/organisations/{$org->uuid}/brands/{$brand->uuid}/social-accounts")
            ->assertOk()->getContent();
        $this->assertStringNotContainsString('fictional-access-secret-abc', $body);
        $this->assertStringNotContainsString('fictional-refresh-secret-abc', $body);
        $this->assertStringNotContainsString('encrypted-db:provider-credentials:', $body);
        $this->assertNotNull($attempt->fresh()->consumed_at);
    }

    public function test_callback_is_single_use_and_same_user_only(): void
    {
        [$owner,$org,$brand] = $this->ownerAndBrand('Social Replay');
        $state = $this->start($owner, $org, $brand);
        $other = User::factory()->create();
        $this->actingAs($other)->get("/integrations/social/tiktok/callback?state={$state}&code=dummy")
            ->assertStatus(410);
        $this->assertNull(SocialOauthAttempt::query()->firstOrFail()->consumed_at);
        $this->fakeTikTok();
        $this->actingAs($owner)->get("/integrations/social/tiktok/callback?state={$state}&code=dummy")->assertRedirect();
        $this->actingAs($owner)->get("/integrations/social/tiktok/callback?state={$state}&code=dummy")
            ->assertStatus(410);
        $this->assertSame(1, SocialAccount::query()->where('organisation_id', $org->id)->count());
    }

    public function test_expired_state_is_rejected_before_any_external_http_call(): void
    {
        [$owner,$org,$brand] = $this->ownerAndBrand('Social Expired');
        $state = $this->start($owner, $org, $brand);
        SocialOauthAttempt::query()->firstOrFail()->forceFill(['expires_at' => now()->subMinute()])->save();
        Http::fake();
        Http::preventStrayRequests();
        $this->actingAs($owner)->get("/integrations/social/tiktok/callback?state={$state}&code=dummy")
            ->assertStatus(410);
        Http::assertNothingSent();
    }

    public function test_cross_brand_account_claim_fails_without_leaking_or_reassigning(): void
    {
        [$owner,$org,$brandA] = $this->ownerAndBrand('Social Brand Isolation');
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/brands", [
            'name' => 'Another Social Brand',
        ])->assertCreated();
        $brandB = Brand::query()->where('organisation_id', $org->id)->where('id', '!=', $brandA->id)->firstOrFail();
        $this->fakeTikTok();
        $stateA = $this->start($owner, $org, $brandA);
        $this->actingAs($owner)->get("/integrations/social/tiktok/callback?state={$stateA}&code=dummy")->assertRedirect();
        $stateB = $this->start($owner, $org, $brandB);
        $this->actingAs($owner)->get("/integrations/social/tiktok/callback?state={$stateB}&code=dummy")
            ->assertStatus(409);
        $this->assertSame(1, SocialAccount::query()->where('organisation_id', $org->id)->count());
        $this->assertSame($brandA->id, SocialAccount::query()->firstOrFail()->brand_id);
    }

    public function test_disconnect_archives_account_and_erases_last_local_token(): void
    {
        [$owner,$org,$brand] = $this->ownerAndBrand('Social Disconnect');
        $this->fakeTikTok();
        $state = $this->start($owner, $org, $brand);
        $this->actingAs($owner)->get("/integrations/social/tiktok/callback?state={$state}&code=dummy")->assertRedirect();
        $account = SocialAccount::query()->where('brand_id', $brand->id)->firstOrFail();
        $this->actingAs($owner)->delete($this->routeFor($org, $brand).'/social-accounts/'.$account->uuid)
            ->assertRedirect();
        $this->assertSame('ARCHIVED', $account->fresh()->status);
        $this->assertDatabaseCount('provider_connection_credentials', 0);
        $this->assertSame('DISCONNECTED', ProviderConnection::query()->firstOrFail()->status);
        $this->actingAs($owner)->delete($this->routeFor($org, $brand).'/social-accounts/'.$account->uuid)
            ->assertStatus(409);
    }

    public function test_youtube_only_discovers_read_only_channels(): void
    {
        [$owner,$org,$brand] = $this->ownerAndBrand('Social YouTube');
        $state = $this->start($owner, $org, $brand, 'youtube');
        Http::fake([
            '*oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'fictional-google-access',
                'refresh_token' => 'fictional-google-refresh',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/youtube.readonly',
            ]),
            '*www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [['id' => 'UCexample-channel', 'snippet' => [
                    'title' => 'Test YouTube Channel', 'customUrl' => '@test-channel',
                ]]],
            ]),
        ]);
        Http::preventStrayRequests();
        $this->actingAs($owner)->get("/integrations/social/youtube/callback?state={$state}&code=dummy")
            ->assertRedirect();
        $account = SocialAccount::query()->where('brand_id', $brand->id)->firstOrFail();
        $this->assertSame('UCexample-channel', $account->external_account_id);
        $this->assertDatabaseHas('social_account_capabilities', [
            'social_account_id' => $account->id, 'capability' => 'video.publish', 'mode' => 'UNAVAILABLE',
        ]);
    }

    public function test_nonmember_cannot_read_or_start_connections_for_foreign_organisation(): void
    {
        [$owner,$org,$brand] = $this->ownerAndBrand('Social Foreign Org');
        $stranger = User::factory()->create();
        $base = $this->routeFor($org, $brand);
        $this->actingAs($stranger)->getJson("/api/v1/organisations/{$org->uuid}/brands/{$brand->uuid}/social-accounts")
            ->assertForbidden();
        $this->actingAs($stranger)->post($base.'/social-connections/tiktok/start')->assertForbidden();
        $this->assertDatabaseCount('social_oauth_attempts', 0);
    }

    public function test_invalid_provider_and_unconfigured_credentials_do_not_create_attempts(): void
    {
        [$owner,$org,$brand] = $this->ownerAndBrand('Social Config Failure');
        $base = $this->routeFor($org, $brand);
        $this->actingAs($owner)->post($base.'/social-connections/unknown/start')->assertNotFound();
        config()->set('social_integrations.providers.tiktok.client_secret', '');
        $this->actingAs($owner)->post($base.'/social-connections/tiktok/start')->assertStatus(503);
        $this->assertDatabaseCount('social_oauth_attempts', 0);
    }
}
