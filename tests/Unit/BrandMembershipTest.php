<?php

namespace Tests\Unit;

use App\Enums\StateEnum;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesBrandMemberships;
use Tests\TestCase;
use Tetranyble\Kinship\Models\Role;

class BrandMembershipTest extends TestCase
{
    use CreatesBrandMemberships;
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Schema
    // ---------------------------------------------------------------

    public function test_it_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('brand_memberships', [
            'organisation_id',
            'brand_id',
            'user_id',
            'role_id',
            'state',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_it_has_no_surrogate_id_or_uuid(): void
    {
        $this->assertFalse(Schema::hasColumn('brand_memberships', 'id'));
        $this->assertFalse(Schema::hasColumn('brand_memberships', 'uuid'));
    }

    // ---------------------------------------------------------------
    // Composite PK
    // ---------------------------------------------------------------

    public function test_user_can_belong_to_brand_only_once(): void
    {
        $brand = Brand::factory()->create();
        $user = User::factory()->create();

        $this->createBrandMembership(['brand_id' => $brand->id, 'user_id' => $user->id]);

        $this->expectException(QueryException::class);

        $this->createBrandMembership(['brand_id' => $brand->id, 'user_id' => $user->id]);
    }

    public function test_user_can_belong_to_multiple_brands_in_same_organisation(): void
    {
        $org = Organisation::factory()->create();
        $brandA = Brand::factory()->forOrganisation($org)->create();
        $brandB = Brand::factory()->forOrganisation($org)->create();
        $user = User::factory()->create();

        $this->createBrandMembership(['brand_id' => $brandA->id, 'user_id' => $user->id]);
        $this->createBrandMembership(['brand_id' => $brandB->id, 'user_id' => $user->id]);

        $this->assertSame(
            2,
            DB::table('brand_memberships')->where('user_id', $user->id)->count()
        );
    }

    public function test_same_brand_can_have_many_members(): void
    {
        $brand = Brand::factory()->create();
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            $this->createBrandMembership(['brand_id' => $brand->id, 'user_id' => $user->id]);
        }

        $this->assertSame(
            3,
            DB::table('brand_memberships')->where('brand_id', $brand->id)->count()
        );
    }

    // ---------------------------------------------------------------
    // FKs / restrict semantics
    // ---------------------------------------------------------------

    public function test_deleting_brand_with_memberships_is_restricted(): void
    {
        $brand = Brand::factory()->create();
        $this->createBrandMembership(['brand_id' => $brand->id]);

        $this->expectException(QueryException::class);
        $brand->delete();
    }

    public function test_deleting_user_with_memberships_is_restricted(): void
    {
        $user = User::factory()->create();
        $this->createBrandMembership(['user_id' => $user->id]);

        $this->expectException(QueryException::class);
        $user->delete();
    }

    public function test_deleting_role_with_memberships_is_restricted(): void
    {
        $this->markTestSkipped();
        $role = Role::factory()->create();
        $this->createBrandMembership(['role_id' => $role->id]);

        $this->expectException(QueryException::class);
        $role->delete();
    }

    public function test_deleting_organisation_with_brand_memberships_is_restricted(): void
    {
        // Restrictions cascade through: org → brand → brand_memberships.
        // Deleting the org should fail because brands still exist (brands FK
        // is restrict). This test guards that indirect path.
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $this->createBrandMembership(['brand_id' => $brand->id]);

        $this->expectException(QueryException::class);
        $org->delete();
    }

    // ---------------------------------------------------------------
    // Defaults
    // ---------------------------------------------------------------

    public function test_default_state_is_applied_by_the_database(): void
    {
        $brand = Brand::factory()->create();
        $user = User::factory()->create();
        $role = Role::factory()->create();

        $this->ensureActiveOrganisationMembership($brand, $user->id, $role->id);

        DB::table('brand_memberships')->insert([
            'organisation_id' => $brand->organisation_id,
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        $row = DB::table('brand_memberships')
            ->where('brand_id', $brand->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertSame(StateEnum::ACTIVE->value, $row->state);
    }

    // ---------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------

    public function test_user_brands_relation(): void
    {
        $user = User::factory()->create();
        $brands = Brand::factory()->count(2)->create();

        foreach ($brands as $brand) {
            $this->createBrandMembership(['brand_id' => $brand->id, 'user_id' => $user->id]);
        }

        $this->assertSame(
            $brands->pluck('id')->sort()->values()->all(),
            $user->brands()->pluck('brands.id')->sort()->values()->all()
        );
    }

    public function test_brand_members_relation(): void
    {
        $brand = Brand::factory()->create();
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            $this->createBrandMembership(['brand_id' => $brand->id, 'user_id' => $user->id]);
        }

        $this->assertCount(3, $brand->members()->get());
    }

    public function test_pivot_metadata_is_accessible(): void
    {
        $brand = Brand::factory()->create();
        $user = User::factory()->create();
        $role = Role::factory()->create();

        $this->createBrandMembership([
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        $pivot = $user->brands()->first()->pivot;

        $this->assertSame($brand->organisation_id, (int) $pivot->organisation_id);
        $this->assertSame($role->id, (int) $pivot->role_id);
        $this->assertSame(StateEnum::ACTIVE->value, $pivot->state);
    }

    public function test_can_filter_brands_by_pivot_state(): void
    {
        $user = User::factory()->create();
        $brandA = Brand::factory()->create();
        $brandB = Brand::factory()->create();

        $this->createBrandMembership([
            'brand_id' => $brandA->id,
            'user_id' => $user->id,
            'state' => StateEnum::ACTIVE->value,
        ]);
        $this->createBrandMembership([
            'brand_id' => $brandB->id,
            'user_id' => $user->id,
            'state' => StateEnum::SUSPENDED->value,
        ]);

        $this->assertSame(
            [$brandA->id],
            $user->brands()
                ->wherePivot('state', StateEnum::ACTIVE->value)
                ->pluck('brands.id')
                ->all()
        );
    }

    // ---------------------------------------------------------------
    // Writes
    // ---------------------------------------------------------------

    public function test_attach_detach_and_pivot_update(): void
    {
        $brand = Brand::factory()->create();
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $new = Role::factory()->create();

        $this->ensureActiveOrganisationMembership($brand, $user->id, $role->id);

        $user->brands()->attach($brand->id, [
            'organisation_id' => $brand->organisation_id,
            'role_id' => $role->id,
            'state' => StateEnum::ACTIVE->value,
        ]);

        $this->assertDatabaseHas('brand_memberships', [
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        $user->brands()->updateExistingPivot($brand->id, ['role_id' => $new->id]);

        $this->assertDatabaseHas('brand_memberships', [
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'role_id' => $new->id,
        ]);

        $user->brands()->detach($brand->id);

        $this->assertDatabaseMissing('brand_memberships', [
            'brand_id' => $brand->id,
            'user_id' => $user->id,
        ]);
    }
}
