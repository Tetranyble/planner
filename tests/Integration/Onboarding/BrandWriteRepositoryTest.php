<?php

namespace Tests\Integration\Onboarding;

use App\Enums\StateEnum;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\BrandWriteRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tetranyble\Kinship\Models\Role;

/** Must execute against disposable MySQL; verifies repository-level tenant isolation. */
final class BrandWriteRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_creator_role_from_a_different_organisation(): void
    {
        $actor = User::factory()->create();
        $target = Organisation::factory()->create();
        $other = Organisation::factory()->create();
        $role = Role::factory()->forWorkspace($other)->create();
        DB::table('organisation_memberships')->insert([
            'organisation_id' => $other->id, 'user_id' => $actor->id,
            'role_id' => $role->id, 'state' => StateEnum::ACTIVE->value,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        try {
            app(BrandWriteRepositoryContract::class)->createForMember(
                $target, $actor, $role->id, 'Impossible cross-tenant brand', null, 'Africa/Lagos',
            );
        } finally {
            $this->assertDatabaseCount('brands', 0);
            $this->assertDatabaseCount('brand_memberships', 0);
        }
    }

    public function test_rejects_revoked_membership_even_with_a_valid_workspace_role(): void
    {
        $actor = User::factory()->create();
        $org = Organisation::factory()->create();
        $role = Role::factory()->forWorkspace($org)->create();
        DB::table('organisation_memberships')->insert([
            'organisation_id' => $org->id, 'user_id' => $actor->id,
            'role_id' => $role->id, 'state' => StateEnum::REVOKED->value,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        app(BrandWriteRepositoryContract::class)->createForMember(
            $org, $actor, $role->id, 'Cannot create', null, 'Africa/Lagos',
        );
    }
}
