<?php

namespace Tests\Integration\Social;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repository\Contracts\ProviderGrantOwnershipRepositoryContract;
use App\Repository\Contracts\ProviderGrantRevocationRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/** Uses real Eloquent factories, InnoDB constraints, row locks and transactions. */
final class GrantRevocationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('social_integrations.providers.tiktok', [
            'client_id' => 'fixture-tiktok-app', 'client_secret' => 'fixture-tiktok-secret',
        ]);
        config()->set('social_integrations.grant_actor_hmac_key', 'fixture-grant-hmac');
    }

    private function fixture(Organisation $org, Brand $brand, string $actorId): array
    {
        $conn = ProviderConnection::factory()->forOrganisation($org)->forProvider('tiktok')->create([
            'external_actor_id' => $actorId, 'status' => 'CONNECTED',
        ]);
        ProviderConnectionCredential::factory()->forConnection($conn)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $conn)->create(['status' => 'ACTIVE']);
        app(ProviderGrantOwnershipRepositoryContract::class)->linkGrant($conn, $actorId);

        return [$conn, $account];
    }

    public function test_preview_counts_linked_accounts_across_brands_but_only_inside_one_organisation(): void
    {
        $org = Organisation::factory()->create();
        $a = Brand::factory()->forOrganisation($org)->create();
        $b = Brand::factory()->forOrganisation($org)->create();
        $owner = User::factory()->create();
        [, $account] = $this->fixture($org, $a, 'same-provider-actor');
        $this->fixture($org, $b, 'same-provider-actor');
        $repo = app(ProviderGrantRevocationRepositoryContract::class);
        $preview = $repo->prepare($org, $a, $account->uuid, $owner->id, 'Remove the entire provider authorization.');
        $this->assertSame(2, (int) $preview->affected_connections);
        $confirmed = $repo->confirm($org, $preview->uuid, $owner->id);
        $this->assertSame('READY', $confirmed->status);
    }

    public function test_new_link_after_preview_must_invalidate_confirmation(): void
    {
        $org = Organisation::factory()->create();
        $a = Brand::factory()->forOrganisation($org)->create();
        $b = Brand::factory()->forOrganisation($org)->create();
        $owner = User::factory()->create();
        [, $account] = $this->fixture($org, $a, 'preview-actor');
        $repo = app(ProviderGrantRevocationRepositoryContract::class);
        $preview = $repo->prepare($org, $a, $account->uuid, $owner->id, 'Preview before another brand is connected.');
        $this->fixture($org, $b, 'preview-actor');
        $this->expectException(ConflictHttpException::class);
        $repo->confirm($org, $preview->uuid, $owner->id);
    }

    public function test_stale_inflight_lease_becomes_uncertain_and_never_replays(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $owner = User::factory()->create();
        [$conn,$account] = $this->fixture($org, $brand, 'stale-actor');
        $repo = app(ProviderGrantRevocationRepositoryContract::class);
        $preview = $repo->prepare($org, $brand, $account->uuid, $owner->id, 'Revoke this fixture grant entirely.');
        $ready = $repo->confirm($org, $preview->uuid, $owner->id);
        $ready->forceFill([
            'status' => 'IN_FLIGHT', 'lease_token' => '3a0ea248-aa28-4806-b611-d64e46670012',
            'lease_until' => now()->subMinute(),
        ])->save();
        $this->assertContains($ready->id, $repo->dueIds(10));
        $this->assertNull($repo->acquire($ready->id));
        $this->assertSame('UNCERTAIN', $ready->fresh()->status);
        $this->assertSame('RESTRICTED', $conn->fresh()->status);
        $this->assertDatabaseHas('provider_connection_credentials',['provider_connection_id' => $conn->id]);
        $this->assertNotContains($ready->id,$repo->dueIds(10));
    }
}
