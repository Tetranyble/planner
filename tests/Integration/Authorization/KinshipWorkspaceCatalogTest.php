<?php

namespace Tests\Integration\Authorization;

use App\Authorization\CurrentOrganisation;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tetranyble\Kinship\Catalog\PermissionCatalogSeeder;
use Tetranyble\Kinship\Models\Role;

/** Real package/database integration: no raw DB inserts or fabricated role IDs. */
final class KinshipWorkspaceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_bootstrap_seeds_all_roles_and_restricts_non_owner_grants(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/api/v1/organisations', [
            'name' => 'Catalog Contract Organisation',
        ])->assertCreated();

        $organisation = Organisation::query()->where('name', 'Catalog Contract Organisation')->firstOrFail();
        foreach (['viewer', 'contributor', 'manager', 'owner'] as $name) {
            $this->assertNotNull(Role::query()->forWorkspace($organisation->id)->where('name', $name)->first());
        }
        $viewer = Role::query()->forWorkspace($organisation->id)->where('name', 'viewer')->firstOrFail();
        $manager = Role::query()->forWorkspace($organisation->id)->where('name', 'manager')->firstOrFail();
        $this->assertTrue($viewer->permissions()->where('name', 'brand.index')->exists());
        $this->assertFalse($viewer->permissions()->where('name', 'brand_membership.store')->exists());
        $this->assertFalse($viewer->permissions()->where('name', 'provider_connection.view')->exists());
        $this->assertTrue($manager->permissions()->where('name', 'brand_membership.store')->exists());
        $this->assertTrue($manager->permissions()->where('name', 'publication.schedule')->exists());
        $this->assertFalse($manager->permissions()->where('name', 'organisation_membership.update')->exists());

        app(CurrentOrganisation::class)->within($owner, (int) $organisation->id, function () use ($owner): void {
            $this->assertTrue($owner->hasPermission('brand_membership.store'));
            $this->assertTrue($owner->hasPermission('integration.manage'));
        });
    }

    public function test_catalog_seed_is_idempotent_for_existing_workspace(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/api/v1/organisations', [
            'name' => 'Catalog Idempotent Organisation',
        ])->assertCreated();
        $organisation = Organisation::query()->where('name', 'Catalog Idempotent Organisation')->firstOrFail();
        $before = Role::query()->forWorkspace($organisation->id)->orderBy('name')->pluck('id', 'name')->all();

        app(PermissionCatalogSeeder::class)->seed((int) $organisation->id, workspaceScoped: true, sync: true);
        $after = Role::query()->forWorkspace($organisation->id)->orderBy('name')->pluck('id', 'name')->all();

        $this->assertSame($before, $after);
    }
}
