<?php

namespace Tests\Feature\Directory;

use App\Authorization\CurrentOrganisation;
use App\Enums\StateEnum;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use Tetranyble\Kinship\Models\Permission;
use Tetranyble\Kinship\Models\Role;

/** Executed by CI against disposable MySQL; no network or real social accounts. */
final class DirectoryEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/organisations')->assertUnauthorized();
        $this->getJson('/api/v1/organisations/00000000-0000-0000-0000-000000000000/brands')->assertUnauthorized();
        $this->get('/organisations')->assertRedirect('/login');
    }

    public function test_organisation_directory_only_returns_active_memberships_in_active_organisations(): void
    {
        $actor = User::factory()->create();
        $orgVisible = Organisation::factory()->create(['name' => 'Visible']);
        $orgSuspended = Organisation::factory()->suspended()->create(['name' => 'Suspended']);
        $orgRevoked = Organisation::factory()->create(['name' => 'Revoked']);
        $orgOther = Organisation::factory()->create(['name' => 'Not a member']);

        $this->addOrganisationMembership($actor, $orgVisible);
        $this->addOrganisationMembership($actor, $orgSuspended);
        $this->addOrganisationMembership($actor, $orgRevoked, StateEnum::REVOKED);

        $this->actingAs($actor)
            ->getJson('/api/v1/organisations')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.name', 'Visible')
            ->assertJsonMissing(['name' => $orgOther->name])
            ->assertJsonMissingPath('data.data.0.id')
            ->assertJsonMissingPath('data.data.0.created_by_user_id');
    }

    public function test_organisation_directory_inertia_page_uses_same_safe_payload(): void
    {
        $actor = User::factory()->create();
        $org = Organisation::factory()->create();
        $this->addOrganisationMembership($actor, $org);

        $this->actingAs($actor)
            ->get('/organisations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organisations/Index')
                ->has('organisations.data', 1)
                ->where('organisations.data.0.uuid', $org->uuid)
                ->missing('organisations.data.0.id')
            );
    }

    public function test_brand_directory_denies_an_active_member_without_the_kinship_permission(): void
    {
        $actor = User::factory()->create();
        $org = Organisation::factory()->create();
        $role = $this->addOrganisationMembership($actor, $org);
        $brand = Brand::factory()->forOrganisation($org)->create();
        $this->addBrandMembership($actor, $org, $brand, $role);

        $this->actingAs($actor)
            ->getJson("/api/v1/organisations/{$org->uuid}/brands")
            ->assertForbidden();
    }

    public function test_brand_directory_returns_only_active_brands_with_active_brand_membership(): void
    {
        $actor = User::factory()->create();
        $org = Organisation::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $role = $this->addOrganisationMembership($actor, $org);
        $this->grantViewPermission($actor, $org, $role);

        $allowed = Brand::factory()->forOrganisation($org)->create(['name' => 'Pensuh']);
        $revoked = Brand::factory()->forOrganisation($org)->create(['name' => 'Revoked']);
        $suspended = Brand::factory()->forOrganisation($org)->suspended()->create(['name' => 'Suspended']);
        Brand::factory()->forOrganisation($org)->create(['name' => 'Not assigned']);
        Brand::factory()->forOrganisation($otherOrg)->create(['name' => 'Other organisation']);

        $this->addBrandMembership($actor, $org, $allowed, $role);
        $this->addBrandMembership($actor, $org, $revoked, $role, StateEnum::REVOKED);
        $this->addBrandMembership($actor, $org, $suspended, $role);

        $this->actingAs($actor)
            ->getJson("/api/v1/organisations/{$org->uuid}/brands")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.name', 'Pensuh')
            ->assertJsonMissingPath('data.data.0.id')
            ->assertJsonMissingPath('data.data.0.settings');
    }

    public function test_brand_directory_inertia_page_uses_safe_brand_fields(): void
    {
        $actor = User::factory()->create();
        $org = Organisation::factory()->create();
        $role = $this->addOrganisationMembership($actor, $org);
        $this->grantViewPermission($actor, $org, $role);
        $brand = Brand::factory()->forOrganisation($org)->create();
        $this->addBrandMembership($actor, $org, $brand, $role);

        $this->actingAs($actor)
            ->get("/organisations/{$org->uuid}/brands")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organisations/Brands')
                ->has('brands.data', 1)
                ->where('brands.data.0.uuid', $brand->uuid)
                ->missing('brands.data.0.settings')
                ->missing('brands.data.0.id')
            );
    }

    public function test_revoked_organisation_membership_blocks_brand_directory_even_with_a_role_grant(): void
    {
        $actor = User::factory()->create();
        $org = Organisation::factory()->create();
        $role = $this->addOrganisationMembership($actor, $org);
        $this->grantViewPermission($actor, $org, $role);
        $brand = Brand::factory()->forOrganisation($org)->create();
        $this->addBrandMembership($actor, $org, $brand, $role);

        DB::table('organisation_memberships')
            ->where('organisation_id', $org->id)
            ->where('user_id', $actor->id)
            ->update(['state' => StateEnum::REVOKED->value]);

        $this->actingAs($actor)
            ->getJson("/api/v1/organisations/{$org->uuid}/brands")
            ->assertForbidden();
    }

    public function test_directory_rejects_unbounded_pagination(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->getJson('/api/v1/organisations?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    private function addOrganisationMembership(User $user, Organisation $org, StateEnum $state = StateEnum::ACTIVE): Role
    {
        $role = Role::factory()->forWorkspace($org)->create();
        DB::table('organisation_memberships')->insert([
            'organisation_id' => $org->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'state' => $state->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $role;
    }

    private function addBrandMembership(User $user, Organisation $org, Brand $brand, Role $role, StateEnum $state = StateEnum::ACTIVE): void
    {
        DB::table('brand_memberships')->insert([
            'organisation_id' => $org->id,
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'state' => $state->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantViewPermission(User $user, Organisation $org, Role $role): void
    {
        $permission = Permission::factory()->create(['name' => 'brand.index']);
        $role->givePermissionTo($permission);
        app(CurrentOrganisation::class)->select($user, $org->id);
        $user->syncRoles($role);
    }
}
