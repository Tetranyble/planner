<?php

namespace Tests\Feature\Platform;

use App\Authorization\CurrentOrganisation;
use App\Enums\StateEnum;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tetranyble\Kinship\Models\Role;

class OrganisationContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_unselected_and_unrelated_organisations_fail_closed(): void
    {
        $user = User::factory()->create();
        $organisation = Organisation::factory()->create();
        $context = app(CurrentOrganisation::class);

        $this->assertNull($context->idFor($user));
        $this->expectException(AuthorizationException::class);
        $context->select($user, $organisation->id);
    }

    public function test_active_membership_is_required_and_revocation_takes_effect(): void
    {
        $user = User::factory()->create();
        $organisation = Organisation::factory()->create();
        $role = Role::factory()->forWorkspace($organisation)->create();
        DB::table('organisation_memberships')->insert([
            'organisation_id' => $organisation->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'state' => StateEnum::ACTIVE->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $context = app(CurrentOrganisation::class);
        $context->select($user, $organisation->id);
        $this->assertSame($organisation->id, $context->idFor($user));
        $this->assertSame($organisation->id, $user->getWorkspaceIdentifier());

        DB::table('organisation_memberships')
            ->where('organisation_id', $organisation->id)
            ->where('user_id', $user->id)
            ->update(['state' => StateEnum::REVOKED->value]);

        $this->assertNull($context->idFor($user));
        $this->assertNull($user->getWorkspaceIdentifier());
    }
}
