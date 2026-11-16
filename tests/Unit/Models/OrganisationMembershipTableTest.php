<?php

namespace Tests\Unit\Models;

use App\Enums\StateEnum;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesOrganisationMemberships;
use Tests\TestCase;
use Tetranyble\Kinship\Models\Role;

class OrganisationMembershipTableTest extends TestCase
{
    use CreatesOrganisationMemberships;
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Schema
    // ---------------------------------------------------------------

    public function test_it_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('organisation_memberships', [
            'organisation_id',
            'user_id',
            'role_id',
            'state',
            'invited_by_user_id',
            'joined_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_it_has_no_surrogate_id_or_uuid(): void
    {
        $this->assertFalse(Schema::hasColumn('organisation_memberships', 'id'));
        $this->assertFalse(Schema::hasColumn('organisation_memberships', 'uuid'));
    }

    // ---------------------------------------------------------------
    // Composite PK
    // ---------------------------------------------------------------

    public function test_composite_primary_key_is_enforced(): void
    {
        $org = Organisation::factory()->create();
        $user = User::factory()->create();

        $this->createMembership([
            'organisation_id' => $org->id,
            'user_id' => $user->id,
        ]);

        $this->expectException(QueryException::class);

        $this->createMembership([
            'organisation_id' => $org->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_same_user_can_belong_to_multiple_organisations(): void
    {
        $user = User::factory()->create();
        $orgA = Organisation::factory()->create();
        $orgB = Organisation::factory()->create();

        $this->createMembership(['user_id' => $user->id, 'organisation_id' => $orgA->id]);
        $this->createMembership(['user_id' => $user->id, 'organisation_id' => $orgB->id]);

        $this->assertSame(
            2,
            DB::table('organisation_memberships')->where('user_id', $user->id)->count()
        );
    }

    public function test_same_organisation_can_have_many_users(): void
    {
        $org = Organisation::factory()->create();
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            $this->createMembership([
                'organisation_id' => $org->id,
                'user_id' => $user->id,
            ]);
        }

        $this->assertSame(
            3,
            DB::table('organisation_memberships')->where('organisation_id', $org->id)->count()
        );
    }

    // ---------------------------------------------------------------
    // FKs / restrict semantics
    // ---------------------------------------------------------------

    public function test_deleting_organisation_with_memberships_is_restricted(): void
    {
        $org = Organisation::factory()->create();
        $this->createMembership(['organisation_id' => $org->id]);

        $this->expectException(QueryException::class);
        $org->delete();
    }

    public function test_deleting_user_with_memberships_is_restricted(): void
    {
        $user = User::factory()->create();
        $this->createMembership(['user_id' => $user->id]);

        $this->expectException(QueryException::class);
        $user->delete();
    }

    public function test_deleting_role_with_memberships_is_restricted(): void
    {
        $this->markTestSkipped();
        $role = Role::factory()->create();
        $this->createMembership(['role_id' => $role->id]);

        $this->expectException(QueryException::class);
        $role->delete();
    }

    public function test_deleting_inviter_with_memberships_is_restricted(): void
    {
        $inviter = User::factory()->create();
        $this->createMembership(['invited_by_user_id' => $inviter->id]);

        $this->expectException(QueryException::class);
        $inviter->delete();
    }

    // ---------------------------------------------------------------
    // Defaults / nullability
    // ---------------------------------------------------------------

    public function test_default_state_is_applied_by_the_database(): void
    {
        $org = Organisation::factory()->create();
        $user = User::factory()->create();
        $role = Role::factory()->create();

        DB::table('organisation_memberships')->insert([
            'organisation_id' => $org->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            // state omitted → DB default
        ]);

        $row = DB::table('organisation_memberships')
            ->where('organisation_id', $org->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertSame(StateEnum::ACTIVE->value, $row->state);
    }

    public function test_joined_at_and_inviter_are_nullable(): void
    {
        $row = $this->createMembership([
            'joined_at' => null,
            'invited_by_user_id' => null,
        ]);

        $fresh = DB::table('organisation_memberships')
            ->where('organisation_id', $row['organisation_id'])
            ->where('user_id', $row['user_id'])
            ->first();

        $this->assertNull($fresh->joined_at);
        $this->assertNull($fresh->invited_by_user_id);
    }

    // ---------------------------------------------------------------
    // belongsToMany access
    // ---------------------------------------------------------------

    public function test_user_organisations_relation(): void
    {
        $user = User::factory()->create();
        $orgs = Organisation::factory()->count(2)->create();

        foreach ($orgs as $org) {
            $this->createMembership(['user_id' => $user->id, 'organisation_id' => $org->id]);
        }

        $this->assertSame(
            $orgs->pluck('id')->sort()->values()->all(),
            $user->organisations()->pluck('organisations.id')->sort()->values()->all()
        );
    }

    public function test_organisation_users_relation(): void
    {
        $org = Organisation::factory()->create();
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            $this->createMembership([
                'organisation_id' => $org->id,
                'user_id' => $user->id,
            ]);
        }

        $this->assertCount(3, $org->users()->get());
    }

    public function test_pivot_metadata_is_accessible(): void
    {
        $user = User::factory()->create();
        $org = Organisation::factory()->create();
        $role = Role::factory()->create();

        $this->createMembership([
            'user_id' => $user->id,
            'organisation_id' => $org->id,
            'role_id' => $role->id,
        ]);

        $pivot = $user->organisations()->first()->pivot;

        $this->assertSame($role->id, (int) $pivot->role_id);
        $this->assertSame(StateEnum::ACTIVE->value, $pivot->state);
        $this->assertNotNull($pivot->joined_at);
    }

    public function test_can_filter_organisations_by_pivot_state(): void
    {
        $user = User::factory()->create();
        $active = Organisation::factory()->create();
        $suspOrg = Organisation::factory()->create();

        $this->createMembership([
            'user_id' => $user->id,
            'organisation_id' => $active->id,
            'state' => StateEnum::ACTIVE->value,
        ]);
        $this->createMembership([
            'user_id' => $user->id,
            'organisation_id' => $suspOrg->id,
            'state' => StateEnum::SUSPENDED->value,
        ]);

        $this->assertSame(
            [$active->id],
            $user->organisations()
                ->wherePivot('state', StateEnum::ACTIVE->value)
                ->pluck('organisations.id')
                ->all()
        );
    }

    // ---------------------------------------------------------------
    // Writes via attach / detach / updateExistingPivot
    // ---------------------------------------------------------------

    public function test_attach_detach_and_pivot_update(): void
    {
        $user = User::factory()->create();
        $org = Organisation::factory()->create();
        $role = Role::factory()->create();
        $new = Role::factory()->create();

        $user->organisations()->attach($org->id, [
            'role_id' => $role->id,
            'state' => StateEnum::ACTIVE->value,
            'joined_at' => now(),
        ]);

        $this->assertDatabaseHas('organisation_memberships', [
            'user_id' => $user->id,
            'organisation_id' => $org->id,
            'role_id' => $role->id,
        ]);

        $user->organisations()->updateExistingPivot($org->id, ['role_id' => $new->id]);

        $this->assertDatabaseHas('organisation_memberships', [
            'user_id' => $user->id,
            'organisation_id' => $org->id,
            'role_id' => $new->id,
        ]);

        $user->organisations()->detach($org->id);

        $this->assertDatabaseMissing('organisation_memberships', [
            'user_id' => $user->id,
            'organisation_id' => $org->id,
        ]);
    }
}
