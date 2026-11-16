<?php

namespace Tests\Feature\Onboarding;

use App\Authorization\CurrentOrganisation;
use App\Enums\StateEnum;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use Tetranyble\Kinship\Models\Role;

/** Endpoint tests for the complete first-owner and creator-brand workflows. */
final class OnboardingEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_cannot_create_organisations_or_brands(): void
    {
        $this->postJson('/api/v1/organisations', ['name' => 'Tetranyble'])->assertUnauthorized();
        $organisation = Organisation::factory()->create();
        $this->postJson("/api/v1/organisations/{$organisation->uuid}/brands", ['name' => 'Pensuh'])->assertUnauthorized();
        $this->get('/organisations/create')->assertRedirect('/login');
    }

    public function test_unverified_users_cannot_provision_organisations_or_brands(): void
    {
        $actor = User::factory()->unverified()->create();
        $organisation = Organisation::factory()->create();

        $this->actingAs($actor)->postJson('/api/v1/organisations', ['name' => 'Attempt'])
            ->assertStatus(409);
        $this->actingAs($actor)->postJson("/api/v1/organisations/{$organisation->uuid}/brands", [
            'name' => 'Attempt',
        ])->assertStatus(409);
        $this->actingAs($actor)->get('/organisations/create')
            ->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('organisation_memberships', 0);
    }

    public function test_organisation_input_is_validated_without_creating_records(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor)->postJson('/api/v1/organisations', [
            'name' => ' ',
            'timezone' => 'Invalid/Timezone',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'timezone']);

        $this->assertDatabaseCount('organisations', 0);
        $this->assertDatabaseCount('organisation_memberships', 0);
    }

    public function test_new_organisation_has_owner_role_preferences_and_audit(): void
    {
        $actor = User::factory()->create();

        $response = $this->actingAs($actor)->postJson('/api/v1/organisations', [
            'name' => 'Tetranyble Labs',
            'timezone' => 'Africa/Lagos',
            'created_by_user_id' => 999999,
            'role_id' => 999999,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Tetranyble Labs')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.created_by_user_id');

        $org = Organisation::query()->firstOrFail();
        $this->assertSame($actor->id, $org->created_by_user_id);
        $this->assertDatabaseHas('organisation_preferences', ['organisation_id' => $org->id]);
        $owner = Role::query()->forWorkspace($org->id)->where('name', 'owner')->firstOrFail();
        $this->assertDatabaseHas('organisation_memberships', [
            'organisation_id' => $org->id,
            'user_id' => $actor->id,
            'role_id' => $owner->id,
            'state' => StateEnum::ACTIVE->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organisation_id' => $org->id,
            'actor_id' => (string) $actor->id,
            'action' => 'organisation.created',
        ]);

        app(CurrentOrganisation::class)->select($actor, $org->id);
        $this->assertTrue($actor->hasPermission('brand.store'));
    }

    public function test_organisation_creation_rolls_back_when_kinship_catalog_is_disabled(): void
    {
        config()->set('kinship.catalog.enabled', false);
        $actor = User::factory()->create();

        try {
            $this->actingAs($actor)->withoutExceptionHandling()->post('/organisations', [
                'name' => 'Cannot complete',
                'timezone' => 'Africa/Lagos',
            ]);
            $this->fail('Expected the Kinship catalogue to reject provisioning.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('catalog seeding is disabled', $exception->getMessage());
        }

        $this->assertDatabaseCount('organisations', 0);
        $this->assertDatabaseCount('organisation_preferences', 0);
        $this->assertDatabaseCount('organisation_memberships', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_owner_can_create_brand_but_cannot_spoof_organisation_or_role(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/api/v1/organisations', [
            'name' => 'Tetranyble',
        ])->assertCreated();
        $org = Organisation::query()->firstOrFail();
        $otherOrg = Organisation::factory()->create();

        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/brands", [
            'name' => 'Pensuh',
            'website_url' => 'https://pensuh.com',
            'organisation_id' => $otherOrg->id,
            'role_id' => 999999,
            'status' => 'CLOSED',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Pensuh')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.settings');

        $brand = Brand::query()->firstOrFail();
        $roleId = DB::table('organisation_memberships')->where('organisation_id', $org->id)
            ->where('user_id', $owner->id)->value('role_id');

        $this->assertSame($org->id, $brand->organisation_id);
        $this->assertTrue($brand->isActive());
        $this->assertDatabaseHas('brand_memberships', [
            'organisation_id' => $org->id,
            'brand_id' => $brand->id,
            'user_id' => $owner->id,
            'role_id' => $roleId,
            'state' => StateEnum::ACTIVE->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organisation_id' => $org->id,
            'brand_ref' => $brand->id,
            'action' => 'brand.created',
        ]);
        $this->actingAs($owner)->getJson("/api/v1/organisations/{$org->uuid}/brands")
            ->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_non_member_cannot_create_brand_in_someone_elses_organisation(): void
    {
        $org = Organisation::factory()->create();
        $attacker = User::factory()->create();

        $this->actingAs($attacker)->postJson("/api/v1/organisations/{$org->uuid}/brands", [
            'name' => 'Unauthorized brand',
        ])->assertForbidden();

        $this->assertDatabaseCount('brands', 0);
    }

    public function test_active_member_without_brand_manage_cannot_create_brand(): void
    {
        $org = Organisation::factory()->create();
        $actor = User::factory()->create();
        $role = Role::factory()->forWorkspace($org)->create();
        DB::table('organisation_memberships')->insert([
            'organisation_id' => $org->id,
            'user_id' => $actor->id,
            'role_id' => $role->id,
            'state' => StateEnum::ACTIVE->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor)->postJson("/api/v1/organisations/{$org->uuid}/brands", [
            'name' => 'No permission',
        ])->assertForbidden();
        $this->assertDatabaseCount('brands', 0);
    }

    public function test_invalid_brand_url_and_timezone_are_rejected(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => 'My Workspace'])->assertCreated();
        $org = Organisation::query()->firstOrFail();

        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/brands", [
            'name' => 'Brand', 'website_url' => 'javascript:alert(1)', 'timezone' => 'Mars/Base',
        ])->assertUnprocessable()->assertJsonValidationErrors(['website_url', 'timezone']);
        $this->assertDatabaseCount('brands', 0);
    }

    public function test_create_forms_are_inertia_pages_and_brand_form_requires_permission(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->get('/organisations/create')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Organisations/Create'));

        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => 'Workspace'])->assertCreated();
        $org = Organisation::query()->firstOrFail();

        $this->actingAs($owner)->get("/organisations/{$org->uuid}/brands/create")
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Organisations/Brands/Create')
            ->where('organisation.uuid', $org->uuid)
            ->missing('organisation.id')
            );

        $this->actingAs(User::factory()->create())
            ->get("/organisations/{$org->uuid}/brands/create")->assertForbidden();
    }
}
