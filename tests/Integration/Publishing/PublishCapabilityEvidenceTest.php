<?php

namespace Tests\Integration\Publishing;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\SocialAccount;
use App\Models\SocialAccountCapability;
use App\Repository\Contracts\SocialPublishCapabilityRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/** MySQL integration: exact account, token generation and stale-grant fencing. */
final class PublishCapabilityEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_scoped_grant_can_gain_short_lived_verified_capability(): void
    {
        config()->set('social_integrations.publishing_entitlements.x', true);
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider('x')
            ->state(['status' => 'CONNECTED', 'scopes' => ['users.read', 'tweet.write'],
                'token_expires_at' => now()->addHours(2)])->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)
            ->state(['provider' => 'x', 'account_kind' => 'CREATOR', 'external_account_id' => '1234567', 'status' => 'ACTIVE'])->create();
        ProviderConnectionCredential::factory()->forConnection($connection)->create();
        $repo = app(SocialPublishCapabilityRepositoryContract::class);
        $ctx = $repo->context($org, $brand, $account->uuid);
        $result = $repo->record($org, $brand, $ctx['id'], $ctx['credential_digest'], 'AUTOMATIC', 'PROVIDER_ACCOUNT_VERIFIED');
        $this->assertSame('AUTOMATIC', $result['mode']);
        $cap = SocialAccountCapability::query()->where('social_account_id', $account->id)
            ->where('capability', 'post.publish')->firstOrFail();
        $this->assertTrue($cap->valid_until->isFuture());
        $this->assertTrue($cap->valid_until->lessThan(now()->addMinutes(31)));
    }

    public function test_rotated_grant_does_not_accept_stale_probe(): void
    {
        config()->set('social_integrations.publishing_entitlements.x', true);
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider('x')
            ->state(['status' => 'CONNECTED', 'scopes' => ['users.read', 'tweet.write'],
                'token_expires_at' => now()->addHours(2)])->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)
            ->state(['account_kind' => 'CREATOR', 'external_account_id' => '1234567', 'status' => 'ACTIVE'])->create();
        $credential = ProviderConnectionCredential::factory()->forConnection($connection)->create();
        $repo = app(SocialPublishCapabilityRepositoryContract::class);
        $old = $repo->context($org, $brand, $account->uuid);
        $credential->forceFill(['access_token_ciphertext' => Crypt::encryptString('rotated-fixture')])->save();
        $this->expectException(ConflictHttpException::class);
        $repo->record($org, $brand, $old['id'], $old['credential_digest'], 'AUTOMATIC', 'PROVIDER_ACCOUNT_VERIFIED');
    }
}
