<?php

namespace Tests\Feature\Membership;

use App\Authorization\CurrentOrganisation;
use App\Enums\StateEnum;
use App\Models\Brand;
use App\Models\BrandMembership;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use App\Services\Membership\OrganisationMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tetranyble\Kinship\Models\Role;

/** Feature tests use domain factories for setup, not raw DB facade insertion. */
final class BrandMembershipEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function provision(User $owner, string $name): array
    {
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => $name])->assertCreated();
        $org = Organisation::query()->where('name', $name)->firstOrFail();
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/brands", [
            'name' => $name.' Brand', 'website_url' => null,
        ])->assertCreated();
        $brand = Brand::query()->where('organisation_id', $org->id)->firstOrFail();

        return [$org, $brand];
    }

    private function addOrgMember(Organisation $org, User $user, string $role): OrganisationMembership
    {
        $kinship = Role::query()->forWorkspace((int) $org->getKey())
            ->where('name', $role)->firstOrFail();

        return OrganisationMembership::factory()
            ->forOrganisation($org)->forUser($user)->withRole($kinship)->create();
    }

    public function test_member_can_be_added_changed_revoked_and_reactivated(): void
    {
        $owner = User::factory()->create();
        [$org, $brand] = $this->provision($owner, 'Example Organisation A');
        $user = User::factory()->create();
        $this->addOrgMember($org, $user, 'manager');
        $url = "/api/v1/organisations/{$org->uuid}/brands/{$brand->uuid}/members";

        $this->actingAs($owner)->postJson($url, ['user_id' => $user->id, 'role' => 'viewer'])->assertCreated();
        $this->assertDatabaseHas('brand_memberships', [
            'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'user_id' => $user->id, 'state' => StateEnum::ACTIVE->value,
        ]);
        $this->actingAs($owner)->postJson($url, ['user_id' => $user->id, 'role' => 'viewer'])->assertStatus(409);
        $this->actingAs($owner)->patchJson("{$url}/{$user->id}/role", ['role' => 'manager'])->assertOk();
        $this->assertSame((int) Role::query()->forWorkspace($org->id)->where('name', 'manager')->firstOrFail()->id,
            (int) BrandMembership::query()->where('organisation_id', $org->id)
                ->where('brand_id', $brand->id)->where('user_id', $user->id)->value('role_id'));
        $this->actingAs($owner)->deleteJson("{$url}/{$user->id}")->assertOk();
        $this->assertDatabaseHas('brand_memberships', [
            'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'user_id' => $user->id, 'state' => StateEnum::REVOKED->value,
        ]);
        $this->actingAs($owner)->postJson($url, ['user_id' => $user->id, 'role' => 'viewer'])->assertCreated();
        $this->assertDatabaseHas('brand_memberships', [
            'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'user_id' => $user->id, 'state' => StateEnum::ACTIVE->value,
        ]);
    }

    public function test_role_cannot_escalate_a_viewer_and_member_must_belong_to_organisation(): void
    {
        $owner = User::factory()->create();
        [$org, $brand] = $this->provision($owner, 'Example Organisation B');
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        $this->addOrgMember($org, $viewer, 'viewer');
        $url = "/api/v1/organisations/{$org->uuid}/brands/{$brand->uuid}/members";

        $this->actingAs($owner)->postJson($url, ['user_id' => $viewer->id, 'role' => 'manager'])
            ->assertUnprocessable();
        $this->actingAs($owner)->postJson($url, ['user_id' => $outsider->id, 'role' => 'viewer'])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('brand_memberships', ['brand_id' => $brand->id, 'user_id' => $viewer->id]);
        $this->assertDatabaseMissing('brand_memberships', ['brand_id' => $brand->id, 'user_id' => $outsider->id]);
    }

    public function test_cross_tenant_brand_route_cannot_disclose_or_change_members(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        [$orgA, $brandA] = $this->provision($a, 'Example Organisation C');
        [$orgB, $brandB] = $this->provision($b, 'Example Organisation D');
        $url = "/api/v1/organisations/{$orgB->uuid}/brands/{$brandB->uuid}/members";
        $this->actingAs($a)->getJson($url)->assertForbidden();
        $this->actingAs($a)->postJson($url, ['user_id' => $a->id, 'role' => 'viewer'])->assertForbidden();
        $this->actingAs($a)->getJson("/api/v1/organisations/{$orgA->uuid}/brands/{$brandB->uuid}/members")
            ->assertNotFound();
        $this->actingAs($a)->getJson("/api/v1/organisations/{$orgA->uuid}/brands/{$brandA->uuid}/members")
            ->assertOk();
    }

    public function test_owner_may_recover_brand_access_without_a_preexisting_brand_membership(): void
    {
        $owner = User::factory()->create();
        [$org, $brand] = $this->provision($owner, 'Example Organisation E');
        $url = "/api/v1/organisations/{$org->uuid}/brands/{$brand->uuid}/members";
        $this->actingAs($owner)->deleteJson("{$url}/{$owner->id}")->assertOk();
        // Organisation owner retains explicit membership administration, not brand content access.
        $this->actingAs($owner)->getJson($url)->assertOk();
        $this->actingAs($owner)->getJson("/api/v1/organisations/{$org->uuid}/brands")
            ->assertOk()->assertJsonFragment(['name' => $brand->name]);
        $this->actingAs($owner)->postJson($url, ['user_id' => $owner->id, 'role' => 'owner'])->assertCreated();
    }

    public function test_brand_role_narrows_organisation_manager_membership_permissions(): void
    {
        $owner = User::factory()->create();
        [$organisation, $brand] = $this->provision($owner, 'Example Organisation F');
        $manager = User::factory()->create();
        $role = Role::query()->forWorkspace($organisation->id)->where('name', 'manager')->firstOrFail();

        $this->actingAs($owner);
        app(CurrentOrganisation::class)->within($owner, (int) $organisation->id, function () use ($organisation, $manager, $role): void {
            app(OrganisationMembershipService::class)->grant($organisation, $manager, $role);
        });

        $url = "/api/v1/organisations/{$organisation->uuid}/brands/{$brand->uuid}/members";
        $this->actingAs($owner)->postJson($url, ['user_id' => $manager->id, 'role' => 'viewer'])->assertCreated();
        $this->actingAs($manager)->getJson($url)->assertForbidden();

        $this->actingAs($owner)->patchJson("{$url}/{$manager->id}/role", ['role' => 'manager'])->assertOk();
        $this->actingAs($manager)->getJson($url)->assertOk();
    }

    public function test_unauthenticated_client_cannot_read_members(): void
    {
        $this->getJson('/api/v1/organisations/unknown/brands/unknown/members')->assertUnauthorized();
    }
}
