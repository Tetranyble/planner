<?php

namespace Tests\Integration\Social;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\SocialAccount;
use App\Models\SocialAccountCapability;
use App\Repository\Contracts\MetaCapabilityInspectionRepositoryContract;
use App\Social\Data\MetaTokenInspection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

final class MetaCapabilityInspectionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $organisation = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($organisation)->create();
        $connection = ProviderConnection::factory()->forOrganisation($organisation)->forProvider('facebook')->create([
            'status' => 'CONNECTED', 'external_actor_id' => 'fixture-actor',
            'last_verified_at' => now()->subDays(2), 'inspection_retry_at' => null,
        ]);
        ProviderConnectionCredential::factory()->forConnection($connection)->create([
            'access_token_ciphertext' => Crypt::encryptString('synthetic-meta-token'),
        ]);
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create([
            'status' => 'ACTIVE', 'external_account_id' => 'fixture-page',
        ]);
        SocialAccountCapability::factory()->forAccount($account)->create([
            'capability' => 'post.publish', 'mode' => 'UNAVAILABLE',
        ]);

        return [$organisation, $connection, $account];
    }

    public function test_verified_token_only_grants_read_not_publishing(): void
    {
        [, $connection,$account] = $this->fixture();
        config()->set('social_integrations.providers.facebook.client_id', 'fixture-app');
        $repo = app(MetaCapabilityInspectionRepositoryContract::class);
        $lease = $repo->acquire($connection->id, 60);
        $this->assertNotNull($lease);
        $this->assertNull($repo->acquire($connection->id, 60));
        $inspection = new MetaTokenInspection(true, 'fixture-app', 'fixture-actor',
            ['pages_show_list', 'pages_read_engagement'], [], time() + 3600, time() + 3600);
        $this->assertTrue($repo->complete($lease, $inspection));
        $this->assertSame('READ_ONLY', SocialAccountCapability::query()
            ->where('social_account_id', $account->id)->where('capability', 'profile.read')->value('mode'));
        $this->assertSame('UNAVAILABLE', SocialAccountCapability::query()
            ->where('social_account_id', $account->id)->where('capability', 'post.publish')->value('mode'));
        $this->assertSame('VERIFIED', $connection->fresh()->last_inspection_result);
    }

    public function test_wrong_actor_restricts_and_downgrades_all_capabilities(): void
    {
        [, $connection,$account] = $this->fixture();
        config()->set('social_integrations.providers.facebook.client_id', 'fixture-app');
        $repo = app(MetaCapabilityInspectionRepositoryContract::class);
        $lease = $repo->acquire($connection->id, 60);
        $this->assertFalse($repo->complete($lease, new MetaTokenInspection(true, 'fixture-app', 'attacker',
            ['pages_show_list'], [], time() + 600, time() + 600)));
        $this->assertSame('REAUTH_REQUIRED', $connection->fresh()->status);
        $this->assertSame('UNAVAILABLE', SocialAccountCapability::query()
            ->where('social_account_id', $account->id)->firstOrFail()->mode);
    }

    public function test_temporary_failure_does_not_mark_token_verified(): void
    {
        [, $connection] = $this->fixture();
        $repo = app(MetaCapabilityInspectionRepositoryContract::class);
        $lease = $repo->acquire($connection->id, 60);
        $repo->fail($lease);
        $this->assertSame('RETRY_PENDING', $connection->fresh()->last_inspection_result);
        $this->assertNull($repo->acquire($connection->id,60));
    }
}
