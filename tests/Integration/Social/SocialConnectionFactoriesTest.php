<?php

namespace Tests\Integration\Social;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\SocialAccount;
use App\Models\SocialAccountCapability;
use App\Models\SocialAccountStatusEvent;
use App\Models\SocialOauthAttempt;
use App\Models\User;
use Database\Seeders\SocialConnectionsDevelopmentSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

final class SocialConnectionFactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoped_social_factories_build_legal_foreign_keys(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create();
        $cap = SocialAccountCapability::factory()->forAccount($account)->create();
        $event = SocialAccountStatusEvent::factory()->forAccount($account)->create();
        $attempt = SocialOauthAttempt::factory()->forBrandAndActor($brand, $actor)->create();
        $secret = ProviderConnectionCredential::factory()->forConnection($connection)->create();
        $this->assertSame($org->id, $account->organisation_id);
        $this->assertSame($brand->id, $cap->brand_id);
        $this->assertSame($account->id, $event->social_account_id);
        $this->assertSame($actor->id, $attempt->user_id);
        $this->assertSame('synthetic-access-token', Crypt::decryptString($secret->access_token_ciphertext));
    }

    public function test_opt_in_seeders_are_idempotent_and_never_generate_credentials(): void
    {
        $org = Organisation::factory()->create();
        Brand::factory()->forOrganisation($org)->create();
        $this->seed(SocialConnectionsDevelopmentSeeder::class);
        $this->seed(SocialConnectionsDevelopmentSeeder::class);
        $this->assertDatabaseCount('provider_connections', 1);
        $this->assertDatabaseCount('social_accounts', 1);
        $this->assertDatabaseCount('social_account_capabilities', 1);
        $this->assertDatabaseCount('social_account_status_events', 1);
        $this->assertDatabaseCount('provider_connection_credentials', 0);
        $this->assertSame('DISCONNECTED', ProviderConnection::query()->firstOrFail()->status);
    }

    public function test_provider_identity_has_database_uniqueness_guard(): void
    {
        $org = Organisation::factory()->create();
        ProviderConnection::factory()->forOrganisation($org)->create(['external_actor_id' => 'same-actor']);
        $this->expectException(QueryException::class);
        ProviderConnection::factory()->forOrganisation($org)->create(['external_actor_id' => 'same-actor']);
    }

    public function test_social_factory_rejects_cross_tenant_associations(): void
    {
        $brand = Brand::factory()->create();
        $foreign = ProviderConnection::factory()->create();
        $this->expectException(\InvalidArgumentException::class);
        SocialAccount::factory()->forBrandAndConnection($brand,$foreign);
    }
}
