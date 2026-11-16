<?php

namespace Tests\Feature\Social;

use App\Models\AnalyticRollup;
use App\Models\AnalyticSnapshot;
use App\Models\ApiLog;
use App\Models\Brand;
use App\Models\IntegrationSyncCursor;
use App\Models\MetaDataDeletionRequest;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\SocialAccount;
use App\Models\SocialOAuthSelection;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Repository\Contracts\MetaDataDeletionRepositoryContract;
use App\Services\Social\HandleMetaDataDeletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/** Signed Meta callback has no authenticated user or Kinship workspace. MySQL required. */
final class MetaDataDeletionCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('social_integrations.providers.facebook.client_secret', 'fixture-meta-secret');
        config()->set('social_integrations.meta_actor_hmac_key', 'fixture-stable-hmac');
        config()->set('app.url', 'https://planner.test');
    }

    private function signed(string $actorId): string
    {
        $encoded = rtrim(strtr(base64_encode(json_encode([
            'algorithm' => 'HMAC-SHA256', 'user_id' => $actorId, 'issued_at' => time(),
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $encoded, 'fixture-meta-secret', true)), '+/', '-_'), '=').'.'.$encoded;
    }

    private function grant(string $provider, string $actor, string $name): array
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider($provider)->create([
            'external_actor_id' => $actor, 'status' => 'CONNECTED',
        ]);
        ProviderConnectionCredential::factory()->forConnection($connection)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create([
            'status' => 'ACTIVE', 'external_account_id' => $name, 'display_name' => $name,
        ]);

        return [$connection, $account];
    }

    public function test_signed_callback_blocks_and_erases_all_matching_meta_grants_across_tenants(): void
    {
        [$fb, $page] = $this->grant('facebook', 'app-actor-777', 'FB Page');
        [$ig, $profile] = $this->grant('instagram', 'app-actor-777', 'IG Business');
        [$unrelated, $other] = $this->grant('facebook', 'different-actor', 'Different User Page');

        $response = $this->post('/api/meta/data-deletion', ['signed_request' => $this->signed('app-actor-777')]);
        $response->assertOk()->assertJsonStructure(['url', 'confirmation_code'])
            ->assertHeader('Cache-Control', 'no-store, private');
        $code = $response->json('confirmation_code');
        $this->assertSame('https://planner.test/meta/data-deletion/status/'.$code, $response->json('url'));
        $this->assertSame(2, MetaDataDeletionRequest::query()->firstOrFail()->matched_grants);
        $this->assertSame('REVOKED', $fb->fresh()->status);
        $this->assertSame('REVOKED', $ig->fresh()->status);
        $this->assertSame('CONNECTED', $unrelated->fresh()->status);
        $this->assertSame('RESTRICTED', $page->fresh()->status);
        $this->assertSame('RESTRICTED', $profile->fresh()->status);
        $this->assertSame('ACTIVE', $other->fresh()->status);
        $this->assertSame(0, ProviderConnectionCredential::query()
            ->whereIn('provider_connection_id', [$fb->id, $ig->id])->count());
        $this->assertSame(1, ProviderConnectionCredential::query()
            ->where('provider_connection_id', $unrelated->id)->count());
        $this->assertSame('PENDING', MetaDataDeletionRequest::query()->firstOrFail()->state);

        $this->assertSame(1, app(HandleMetaDataDeletion::class)->processPending()['completed']);
        $this->assertSame('COMPLETED', MetaDataDeletionRequest::query()->firstOrFail()->state);
        $this->assertNull($page->fresh()->handle);
        $this->assertStringStartsWith('erased:', $page->fresh()->external_account_id);
        $this->assertStringStartsWith('erased:', $fb->fresh()->external_actor_id);
        $this->get('/meta/data-deletion/status/'.$code)->assertOk()->assertSee('Completed')
            ->assertDontSee('app-actor-777')->assertDontSee('FB Page');
    }

    public function test_callback_is_idempotent_and_unknown_actor_can_be_acknowledged(): void
    {
        $signed = $this->signed('unknown-meta-actor');
        $first = $this->post('/api/meta/data-deletion', ['signed_request' => $signed])->assertOk();
        $second = $this->post('/api/meta/data-deletion', ['signed_request' => $signed])->assertOk();
        $this->assertSame($first->json('confirmation_code'), $second->json('confirmation_code'));
        $this->assertSame(1, MetaDataDeletionRequest::query()->count());
        $this->assertSame('COMPLETED', MetaDataDeletionRequest::query()->firstOrFail()->state);
    }

    public function test_invalid_signature_never_creates_a_deletion_request(): void
    {
        $this->post('/api/meta/data-deletion', ['signed_request' => 'not-a-signed-request'])->assertStatus(400);
        $this->assertSame(0, MetaDataDeletionRequest::query()->count());
    }

    public function test_existing_oauth_is_blocked_when_deletion_is_outstanding(): void
    {
        $this->grant('facebook', 'pending-actor', 'Test Page');
        $this->post('/api/meta/data-deletion', ['signed_request' => $this->signed('pending-actor')])->assertOk();
        $this->assertTrue(app(MetaDataDeletionRepositoryContract::class)
            ->blocksExistingOAuth('pending-actor', now()->subMinute()));
    }

    public function test_public_status_requires_a_valid_confirmation_code(): void
    {
        $this->get('/meta/data-deletion/status/'.str_repeat('Z', 48))->assertNotFound();
    }

    public function test_outstanding_oauth_selection_is_invalidated_only_for_matching_actor(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $member = User::factory()->create();
        $pending = SocialOAuthSelection::factory()->forBrandAndActor($brand, $member)->create();
        $unrelated = SocialOAuthSelection::factory()->forBrandAndActor($brand, $member)->create([
            'grant_ciphertext' => Crypt::encryptString(json_encode([
                'accessToken' => 'synthetic-unrelated', 'refreshToken' => null,
                'actorId' => 'another-actor', 'scopes' => ['pages_show_list'],
                'expiresIn' => 3600, 'refreshExpiresIn' => null,
            ], JSON_THROW_ON_ERROR)),
        ]);
        $this->post('/api/meta/data-deletion', ['signed_request' => $this->signed('fixture-user')])->assertOk();
        $this->assertNotNull($pending->fresh()->consumed_at);
        $this->assertSame('purged', Crypt::decryptString($pending->fresh()->grant_ciphertext));
        $this->assertNull($unrelated->fresh()->consumed_at);
    }

    public function test_a_second_callback_can_not_leak_an_existing_request_identity_or_status(): void
    {
        $first = $this->post('/api/meta/data-deletion', ['signed_request' => $this->signed('someone-else')])->assertOk();
        $this->get('/meta/data-deletion/status/'.str_repeat('A', 48))->assertNotFound();
        $this->get('/meta/data-deletion/status/'.$first->json('confirmation_code'))
            ->assertOk()->assertDontSee('someone-else')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_provider_derived_snapshots_logs_and_sync_cursors_are_erased(): void
    {
        [$connection, $account] = $this->grant('instagram', 'erase-provider-data', 'Original IG Profile');
        $snapshot = AnalyticSnapshot::factory()->forAccount($account)->create();
        $rollup = AnalyticRollup::factory()->forAccount($account)->create();
        $cursor = IntegrationSyncCursor::factory()->forAccount($account)->create();
        $webhook = WebhookEvent::factory()->forAccount($account)->create();
        $apiLog = ApiLog::factory()->forAccount($account)->create();
        $this->post('/api/meta/data-deletion', ['signed_request' => $this->signed('erase-provider-data')])->assertOk();
        app(HandleMetaDataDeletion::class)->processPending();
        $this->assertModelMissing($snapshot);
        $this->assertModelMissing($rollup);
        $this->assertModelMissing($cursor);
        $this->assertModelMissing($webhook);
        $this->assertModelMissing($apiLog);
        $this->assertSame('COMPLETED', MetaDataDeletionRequest::query()->firstOrFail()->state);
    }

    public function test_unattributable_meta_webhook_keeps_deletion_open_for_manual_review(): void
    {
        [, $account] = $this->grant('facebook', 'needs-review-actor', 'Business Page');
        WebhookEvent::factory()->forAccount($account)->create(['social_account_id' => null]);
        $response = $this->post('/api/meta/data-deletion', [
            'signed_request' => $this->signed('needs-review-actor'),
        ])->assertOk();
        app(HandleMetaDataDeletion::class)->processPending();
        $row = MetaDataDeletionRequest::query()->firstOrFail();
        $this->assertSame('NEEDS_REVIEW', $row->state);
        $this->assertNull($row->completed_at);
        $this->get('/meta/data-deletion/status/'.$response->json('confirmation_code'))
            ->assertOk()->assertSee('Additional review required');
    }
}
