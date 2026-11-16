<?php

namespace Tests\Integration\Social;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\SocialAccount;
use App\Models\SocialAccountCapability;
use App\Repository\Contracts\ProviderTokenLifecycleRepositoryContract;
use App\Repository\Contracts\SocialConnectionRepositoryContract;
use App\Services\Social\RenewSocialCredentials;
use App\Social\Data\DiscoveredSocialAccount;
use App\Social\Data\OAuthGrant;
use App\Social\Data\TokenRefreshResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TokenLifecycleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function connected(string $provider, bool $hasRefresh = true): ProviderConnection
    {
        $organisation = Organisation::factory()->create();
        $connection = ProviderConnection::factory()->forOrganisation($organisation)->forProvider($provider)->create([
            'status' => 'CONNECTED', 'token_expires_at' => now()->addMinutes(3),
        ]);
        ProviderConnectionCredential::factory()->forConnection($connection)->create([
            'refresh_token_ciphertext' => $hasRefresh ? Crypt::encryptString('old-credential') : null,
        ]);

        return $connection;
    }

    public function test_rotated_token_is_persisted_atomically_with_new_expiry(): void
    {
        config()->set('social_integrations.providers.tiktok', ['client_id' => 'test-key', 'client_secret' => 'test-secret']);
        $connection = $this->connected('tiktok');
        Http::fake(['*open.tiktokapis.com/*' => Http::response([
            'access_token' => 'rotated-access', 'refresh_token' => 'rotated-refresh',
            'expires_in' => 7200, 'refresh_expires_in' => 15552000, 'scope' => 'user.info.basic',
            'open_id' => $connection->external_actor_id,
        ])]);
        Http::preventStrayRequests();
        $result = app(RenewSocialCredentials::class)->run();
        $this->assertSame(1, $result['renewed']);
        $this->assertSame('CONNECTED', $connection->fresh()->status);
        $this->assertNull($connection->fresh()->refresh_lease_token);
        $this->assertTrue($connection->fresh()->token_expires_at->isAfter(now()->addHour()));
        $credentials = ProviderConnectionCredential::query()->where('provider_connection_id', $connection->id)->firstOrFail();
        $this->assertSame('rotated-refresh', Crypt::decryptString($credentials->refresh_token_ciphertext));
        $this->assertStringNotContainsString('rotated-refresh', $credentials->toJson());
        $this->assertSame([], app(ProviderTokenLifecycleRepositoryContract::class)->dueIds(10, 20));
    }

    public function test_access_only_meta_grant_restricts_accounts_and_capabilities(): void
    {
        $connection = $this->connected('facebook', false);
        $brand = Brand::factory()->forOrganisation(Organisation::findOrFail($connection->organisation_id))->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create(['status' => 'ACTIVE']);
        SocialAccountCapability::factory()->forAccount($account)->create();
        $this->assertNull(app(ProviderTokenLifecycleRepositoryContract::class)->acquire($connection->id, 20));
        $this->assertSame('REAUTH_REQUIRED', $connection->fresh()->status);
        $this->assertSame('RESTRICTED', $account->fresh()->status);
        $this->assertSame('UNAVAILABLE', $account->capabilities()->firstOrFail()->mode);
        $this->assertDatabaseHas('social_account_status_events', ['social_account_id' => $account->id, 'event_type' => 'PROVIDER_REAUTH_REQUIRED']);
    }

    public function test_stale_lease_is_never_replayed_after_crash(): void
    {
        $connection = $this->connected('x');
        $connection->forceFill([
            'refresh_lease_token' => 'old-worker-token', 'refresh_lease_until' => now()->subMinute(),
        ])->save();
        $this->assertNull(app(ProviderTokenLifecycleRepositoryContract::class)->acquire($connection->id, 20));
        $this->assertSame('REAUTH_REQUIRED', $connection->fresh()->status);
        $this->assertSame('REFRESH_OUTCOME_UNKNOWN', $connection->fresh()->reconnect_reason);
    }

    public function test_two_workers_cannot_claim_same_refresh_lease(): void
    {
        $connection = $this->connected('youtube');
        $repo = app(ProviderTokenLifecycleRepositoryContract::class);
        $first = $repo->acquire($connection->id, 20);
        $this->assertNotNull($first);
        $this->assertNull($repo->acquire($connection->id, 20));
        $repo->fail($first, 'PROVIDER_OUTCOME_UNCERTAIN', false);
        $this->assertSame('REAUTH_REQUIRED', $connection->fresh()->status);
    }

    public function test_known_rate_limit_releases_lease_for_later_retry(): void
    {
        $connection = $this->connected('youtube');
        $repo = app(ProviderTokenLifecycleRepositoryContract::class);
        $lease = $repo->acquire($connection->id, 20);
        $repo->fail($lease, 'RATE_LIMIT', true);
        $this->assertNull($connection->fresh()->refresh_lease_token);
        $this->assertSame('CONNECTED', $connection->fresh()->status);
        $this->assertTrue($connection->fresh()->refresh_retry_at->isFuture());
        $this->assertSame([], $repo->dueIds(10, 20));
    }

    public function test_identity_mismatch_from_rotated_token_fails_closed(): void
    {
        $connection = $this->connected('tiktok');
        $repository = app(ProviderTokenLifecycleRepositoryContract::class);
        $lease = $repository->acquire($connection->id, 20);
        $this->assertNotNull($lease);
        $completed = $repository->complete($lease, new TokenRefreshResult(
            'unexpected-token', 'rotated-token', 3600, 180000, null, 'different-open-id',
        ));
        $this->assertFalse($completed);
        $this->assertSame('REAUTH_REQUIRED', $connection->fresh()->status);
        $this->assertSame('PROVIDER_IDENTITY_CHANGED', $connection->fresh()->reconnect_reason);
        $stored = ProviderConnectionCredential::query()->where('provider_connection_id', $connection->id)->firstOrFail();
        $this->assertSame('old-credential', Crypt::decryptString($stored->refresh_token_ciphertext));
    }

    public function test_connection_not_due_is_not_claimed(): void
    {
        $connection = $this->connected('youtube');
        $connection->forceFill(['token_expires_at' => now()->addDays(10)])->save();
        $repository = app(ProviderTokenLifecycleRepositoryContract::class);
        $this->assertSame([], $repository->dueIds(20, 20));
        $this->assertNull($repository->acquire($connection->id, 20));
    }

    public function test_reconnect_replaces_invalid_refresh_instead_of_inheriting_it(): void
    {
        $connection = $this->connected('youtube');
        $organisation = Organisation::query()->findOrFail($connection->organisation_id);
        $brand = Brand::factory()->forOrganisation($organisation)->create();
        $connection->forceFill(['status' => 'REAUTH_REQUIRED'])->save();
        $grant = new OAuthGrant('fresh-access', null, (string) $connection->external_actor_id,
            ['https://www.googleapis.com/auth/youtube.readonly'], 3600);
        $accounts = [new DiscoveredSocialAccount('UC-fixture', 'CHANNEL', 'Test Channel')];
        app(SocialConnectionRepositoryContract::class)->connect(
            $organisation, $brand, 'youtube', $grant, $accounts,
            fn () => ['profile.read' => ['mode' => 'READ_ONLY', 'reason' => 'Read scope']],
        );
        $credential = ProviderConnectionCredential::query()->where('provider_connection_id', $connection->id)->firstOrFail();
        $this->assertSame('fresh-access', Crypt::decryptString($credential->access_token_ciphertext));
        $this->assertNull($credential->refresh_token_ciphertext);
        $this->assertSame('CONNECTED', $connection->fresh()->status);
    }
}
