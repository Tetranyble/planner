<?php

namespace Tests\Integration\Repository;

use App\Enums\StateEnum;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\BrandDirectoryRepositoryContract;
use App\Repository\Contracts\OrganisationDirectoryRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tetranyble\Kinship\Models\Role;

/** Integration checks for SQL-level scope; requires a real MySQL test DB. */
final class DirectoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_organisation_directory_does_not_return_another_users_membership(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $a = Organisation::factory()->create();
        $b = Organisation::factory()->create();
        $this->member($actor, $a);
        $this->member($other, $b);

        $result = app(OrganisationDirectoryRepositoryContract::class)->paginateActiveForMember($actor, 20);
        $this->assertSame(1, $result->total());
        $this->assertSame($a->uuid, $result->items()[0]->uuid);
    }

    public function test_brand_directory_requires_same_org_and_member_and_filters_revocation(): void
    {
        $actor = User::factory()->create();
        $org = Organisation::factory()->create();
        $other = Organisation::factory()->create();
        $role = $this->member($actor, $org);
        $allowed = Brand::factory()->forOrganisation($org)->create();
        $hidden = Brand::factory()->forOrganisation($org)->create();
        Brand::factory()->forOrganisation($other)->create();

        DB::table('brand_memberships')->insert([
            ['organisation_id' => $org->id, 'brand_id' => $allowed->id, 'user_id' => $actor->id, 'role_id' => $role->id, 'state' => StateEnum::ACTIVE->value, 'created_at' => now(), 'updated_at' => now()],
            ['organisation_id' => $org->id, 'brand_id' => $hidden->id, 'user_id' => $actor->id, 'role_id' => $role->id, 'state' => StateEnum::REVOKED->value, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $repo = app(BrandDirectoryRepositoryContract::class);
        $this->assertSame(1, $repo->paginateActiveForMember($actor, $org->id, 20)->total());
        $this->assertSame(0, $repo->paginateActiveForMember($actor, $other->id, 20)->total());
        $this->assertSame($allowed->uuid, $repo->paginateActiveForMember($actor, $org->id, 20)->items()[0]->uuid);
    }

    private function member(User $user, Organisation $org): Role
    {
        $role = Role::factory()->forWorkspace($org)->create();
        DB::table('organisation_memberships')->insert([
            'organisation_id' => $org->id, 'user_id' => $user->id, 'role_id' => $role->id,
            'state' => StateEnum::ACTIVE->value, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $role;
    }
}
