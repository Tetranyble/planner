<?php

namespace Tests\Concerns;

use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Tetranyble\Kinship\Models\Role;

/** Test data goes through Eloquent factories. DB facade is reserved for explicit SQL semantics tests. */
trait CreatesOrganisationMemberships
{
    protected function createMembership(array $attributes = []): array
    {
        $org = isset($attributes['organisation_id'])
            ? Organisation::query()->findOrFail($attributes['organisation_id'])
            : Organisation::factory()->create();
        $user = isset($attributes['user_id'])
            ? User::query()->findOrFail($attributes['user_id'])
            : User::factory()->create();
        $role = isset($attributes['role_id'])
            ? Role::query()->findOrFail($attributes['role_id'])
            : Role::factory()->forWorkspace($org)->create();

        return OrganisationMembership::factory()->forOrganisation($org)
            ->forUser($user)->withRole($role)->create($attributes)->getAttributes();
    }
}
