<?php

namespace Tests\Integration\Membership;

use App\Models\Brand;
use App\Models\BrandMembership;
use App\Models\Organisation;
use App\Models\OrganisationInvitation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tetranyble\Kinship\Models\Role;

/** Factories demonstrate valid composite tenant relationships without raw SQL setup. */
final class BrandMembershipFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_membership_factory_uses_existing_organisation_membership(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $user = User::factory()->create();
        $role = Role::factory()->forWorkspace($org)->create(['name' => 'viewer']);
        $orgMembership = OrganisationMembership::factory()
            ->forOrganisation($org)->forUser($user)->withRole($role)->create();
        $brandMembership = BrandMembership::factory()
            ->forExistingOrganisationMember($orgMembership)->forBrand($brand)->create();
        $this->assertSame((int) $org->getKey(), (int) $brandMembership->organisation_id);
        $this->assertDatabaseHas('brand_memberships', [
            'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'user_id' => $user->id, 'role_id' => $role->id,
        ]);
    }

    public function test_invitation_factory_uses_hashed_token_and_workspaced_role(): void
    {
        $org = Organisation::factory()->create();
        $role = Role::factory()->forWorkspace($org)->create(['name' => 'viewer']);
        $invitation = OrganisationInvitation::factory()->forOrganisationAndRole($org, $role)->create();
        $this->assertSame($org->id, $invitation->organisation_id);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $invitation->token_hash);
        $this->assertArrayNotHasKey('token_hash', $invitation->toArray());
    }
}
