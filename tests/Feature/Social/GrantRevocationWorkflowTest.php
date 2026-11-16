<?php

namespace Tests\Feature\Social;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\ProviderGrantRevocationRequest;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repository\Contracts\ProviderGrantOwnershipRepositoryContract;
use App\Services\Social\ProcessGrantRevocations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Real route middleware and repository queries; provider HTTP fake only. */
final class GrantRevocationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('social_integrations.providers.tiktok', [
            'client_id' => 'testing-tiktok-app', 'client_secret' => 'testing-tiktok-secret',
        ]);
        config()->set('social_integrations.grant_actor_hmac_key', 'testing-stable-grant-key');
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

    private function connected(Organisation $org, Brand $brand, string $actorId = 'test-tiktok-actor'): array
    {
        $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider('tiktok')
            ->create(['external_actor_id' => $actorId, 'status' => 'CONNECTED']);
        ProviderConnectionCredential::factory()->forConnection($connection)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)
            ->create(['status' => 'ACTIVE']);
        app(ProviderGrantOwnershipRepositoryContract::class)->linkGrant($connection, $actorId);

        return [$connection, $account];
    }

    private function prepare(User $owner, Organisation $org, Brand $brand, SocialAccount $account)
    {
        return $this->actingAs($owner)->postJson(
            "/api/v1/organisations/{$org->uuid}/brands/{$brand->uuid}/social-accounts/{$account->uuid}/grant-revocations",
            ['reason' => 'User asked to remove this entire OAuth authorization.']);
    }

    public function test_owner_must_review_impact_before_confirming_and_worker_acknowledges_once(): void
    {
        [$owner,$org,$brand] = $this->ownerAndBrand('Grant Workflow Org');
        [$conn,$account] = $this->connected($org, $brand);
        $preview = $this->prepare($owner, $org, $brand, $account)->assertCreated();
        $this->assertSame('PREPARED', $preview->json('data.status'));
        $this->assertSame(1, $preview->json('data.affected_connections'));
        $uuid = $preview->json('data.uuid');
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/grant-revocations/{$uuid}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'READY');
        $this->assertDatabaseHas('provider_connection_credentials', ['provider_connection_id' => $conn->id]);
        config()->set('social_integrations.remote_revocation_enabled', true);
        Http::fake(['*open.tiktokapis.com/v2/oauth/revoke/*' => Http::response([], 200)]);
        Http::preventStrayRequests();
        $result = app(ProcessGrantRevocations::class)->run(20);
        $this->assertSame(1, $result['acknowledged']);
        $this->assertSame('ACKNOWLEDGED', ProviderGrantRevocationRequest::query()->where('uuid', $uuid)->firstOrFail()->status);
        $this->assertDatabaseMissing('provider_connection_credentials', ['provider_connection_id' => $conn->id]);
        $this->assertSame('REVOKED', $conn->fresh()->status);
        $this->assertSame('RESTRICTED', $account->fresh()->status);
        $this->assertSame(0, app(ProcessGrantRevocations::class)->run(20)['attempted']);
    }

    public function test_unrelated_user_cannot_request_revocation_of_an_organisation_grant(): void
    {
        [$owner,$org,$brand] = $this->ownerAndBrand('Revoke Permission Isolation');
        [, $account] = $this->connected($org, $brand);
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->postJson(
            "/api/v1/organisations/{$org->uuid}/brands/{$brand->uuid}/social-accounts/{$account->uuid}/grant-revocations",
            ['reason' => 'Unauthorized request to revoke another organisation grant.'])->assertForbidden();
        $this->assertSame(0, ProviderGrantRevocationRequest::query()->count());
    }

    public function test_cross_organisation_shared_grant_is_blocked_before_any_external_call(): void
    {
        [$owner,$org,$brand] = $this->ownerAndBrand('Grant Scope One');
        [, $account] = $this->connected($org, $brand, 'shared-account-actor');
        $another = Organisation::factory()->create();
        $connection = ProviderConnection::factory()->forOrganisation($another)->forProvider('tiktok')
            ->create(['external_actor_id' => 'shared-account-actor', 'status' => 'CONNECTED']);
        app(ProviderGrantOwnershipRepositoryContract::class)->linkGrant($connection, 'shared-account-actor');
        $this->prepare($owner, $org, $brand, $account)->assertStatus(409);
        $this->assertSame(0, ProviderGrantRevocationRequest::query()->count());
    }

    public function test_legacy_unlinked_connection_with_same_actor_blocks_remote_revocation(): void
    {
        [$owner,$org,$brand] = $this->ownerAndBrand('Legacy Safety Scope');
        [, $account] = $this->connected($org, $brand, 'actor-with-legacy-connection');
        $other = Organisation::factory()->create();
        ProviderConnection::factory()->forOrganisation($other)->forProvider('tiktok')->create([
            'external_actor_id' => 'actor-with-legacy-connection', 'status' => 'CONNECTED',
        ]);
        $this->prepare($owner, $org, $brand, $account)->assertStatus(409);
        $this->assertSame(0, ProviderGrantRevocationRequest::query()->count());
    }

    public function test_meta_grant_confirmation_is_manual_and_does_not_call_provider(): void
    {
        config()->set('social_integrations.providers.facebook', [
            'client_id' => 'fixture-meta-app', 'client_secret' => 'fixture-meta-secret',
        ]);
        config()->set('social_integrations.meta_actor_hmac_key', 'fixture-meta-key');
        [$owner,$org,$brand] = $this->ownerAndBrand('Meta Manual Revocation');
        $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider('facebook')
            ->create(['external_actor_id' => 'meta-actor-abc', 'status' => 'CONNECTED']);
        ProviderConnectionCredential::factory()->forConnection($connection)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)
            ->create(['status' => 'ACTIVE']);
        app(ProviderGrantOwnershipRepositoryContract::class)->linkMeta($connection, 'meta-actor-abc');
        $uuid = $this->prepare($owner, $org, $brand, $account)->assertCreated()->json('data.uuid');
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/grant-revocations/{$uuid}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'MANUAL_REQUIRED');
        config()->set('social_integrations.remote_revocation_enabled', true);
        Http::preventStrayRequests();
        $this->assertSame(0, app(ProcessGrantRevocations::class)->run()['attempted']);
        $this->assertDatabaseHas('provider_connection_credentials', ['provider_connection_id' => $connection->id]);
    }

    public function test_preview_expires_and_confirmation_is_not_replayable(): void
    {
        [$owner,$org,$brand] = $this->ownerAndBrand('Grant Preview Expiry');
        [, $account] = $this->connected($org, $brand);
        $uuid = $this->prepare($owner, $org, $brand, $account)->assertCreated()->json('data.uuid');
        ProviderGrantRevocationRequest::query()->where('uuid', $uuid)->firstOrFail()
            ->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/grant-revocations/{$uuid}/confirm")
            ->assertStatus(409);
    }

    public function test_provider_failure_becomes_uncertain_and_is_not_retried(): void
    {
        [$owner,$org,$brand] = $this->ownerAndBrand('Grant Remote Uncertain');
        [$connection,$account] = $this->connected($org, $brand);
        $uuid = $this->prepare($owner, $org, $brand, $account)->assertCreated()->json('data.uuid');
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/grant-revocations/{$uuid}/confirm")
            ->assertOk();
        config()->set('social_integrations.remote_revocation_enabled', true);
        Http::fake(['*open.tiktokapis.com/v2/oauth/revoke/*' => Http::response(['error' => 'temporarily_unavailable'], 503)]);
        $first = app(ProcessGrantRevocations::class)->run();
        $this->assertSame(1, $first['uncertain']);
        $this->assertSame(0, app(ProcessGrantRevocations::class)->run()['attempted']);
        $this->assertSame('UNCERTAIN', ProviderGrantRevocationRequest::query()->where('uuid', $uuid)->firstOrFail()->status);
        $this->assertSame('RESTRICTED', $connection->fresh()->status);
    }

    public function test_remote_execution_is_off_by_default(): void
    {
        config()->set('social_integrations.remote_revocation_enabled',false);
        $result = app(ProcessGrantRevocations::class)->run();
        $this->assertSame(1,$result['disabled']);
        $this->assertSame(0,$result['attempted']);
    }
}
