<?php

namespace Tests\Integration\Social;

use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderGrantGroup;
use App\Models\ProviderGrantLink;
use App\Repository\Contracts\ProviderGrantOwnershipRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MetaGrantOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('social_integrations.providers.facebook.client_id', 'fixture-meta-app');
        config()->set('social_integrations.meta_actor_hmac_key', 'test-stable-hmac-key');
    }

    public function test_one_app_scoped_meta_actor_links_connections_across_organisations_without_exposing_actor(): void
    {
        $first = ProviderConnection::factory()->forOrganisation(Organisation::factory()->create())
            ->forProvider('facebook')->create(['external_actor_id' => 'test-meta-actor']);
        $second = ProviderConnection::factory()->forOrganisation(Organisation::factory()->create())
            ->forProvider('instagram')->create(['external_actor_id' => 'test-meta-actor']);
        $unrelated = ProviderConnection::factory()->forOrganisation(Organisation::factory()->create())
            ->forProvider('facebook')->create(['external_actor_id' => 'another-actor']);
        $ownership = app(ProviderGrantOwnershipRepositoryContract::class);
        $ownership->linkMeta($first, 'test-meta-actor');
        $ownership->linkMeta($second, 'test-meta-actor');
        $ownership->linkMeta($unrelated, 'another-actor');
        $this->assertCount(2, $ownership->metaConnectionIds('test-meta-actor'));
        $this->assertSame(2, ProviderGrantGroup::query()->count());
        $this->assertSame(3, ProviderGrantLink::query()->count());
        $this->assertStringNotContainsString('test-meta-actor', ProviderGrantGroup::query()->firstOrFail()->actor_digest);
        $ownership->markDetached($first);
        $this->assertCount(2, $ownership->metaConnectionIds('test-meta-actor'));
    }
}
