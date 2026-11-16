<?php

namespace Tests\Concerns;

use App\Enums\StateEnum;
use App\Models\Brand;
use App\Models\BrandMembership;
use App\Models\OrganisationMembership;
use App\Models\User;
use Tetranyble\Kinship\Models\Role;

/** Valid FK test fixtures use model factories and relationships, never direct DB::insert. */
trait CreatesBrandMemberships
{
    protected function createBrandMembership(array $attributes = []): array
    {
        $brand = isset($attributes['brand_id'])
            ? Brand::query()->findOrFail($attributes['brand_id'])
            : Brand::factory()->create();
        $user = isset($attributes['user_id'])
            ? User::query()->findOrFail($attributes['user_id'])
            : User::factory()->create();
        $orgMember = OrganisationMembership::query()
            ->where('organisation_id', $brand->organisation_id)->where('user_id', $user->id)->first();
        $role = isset($attributes['role_id'])
            ? Role::query()->findOrFail($attributes['role_id'])
            : ($orgMember !== null ? Role::query()->findOrFail($orgMember->role_id)
                : Role::factory()->forWorkspace($brand->organisation)->create());

        $this->ensureActiveOrganisationMembership($brand, $user->id, (int) $role->getKey());
        $member = OrganisationMembership::query()->where('organisation_id', $brand->organisation_id)
            ->where('user_id', $user->id)->firstOrFail();

        return BrandMembership::factory()->forExistingOrganisationMember($member)
            ->forBrand($brand)->create($attributes)->getAttributes();
    }

    protected function ensureActiveOrganisationMembership(Brand $brand, int $userId, int $roleId): void
    {
        $org = $brand->organisation;
        $present = $org->users()->whereKey($userId)->first();
        $payload = ['role_id' => $roleId, 'state' => StateEnum::ACTIVE->value, 'joined_at' => now()];
        if ($present) {
            $org->users()->updateExistingPivot($userId, $payload);

            return;
        }
        $org->users()->attach($userId, $payload);
    }
}
