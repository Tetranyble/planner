<?php

namespace App\Repository\Eloquent;

use App\Enums\StateEnum;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\BrandMemberRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tetranyble\Kinship\Models\Role;

/** All membership queries include both organisation and brand identifiers. */
final class BrandMemberRepository implements BrandMemberRepositoryContract
{
    public function lockOrganisation(Organisation $organisation): Organisation
    {
        return Organisation::query()->whereKey($organisation->getKey())->lockForUpdate()->firstOrFail();
    }

    public function lockBrand(Organisation $organisation, Brand $brand): Brand
    {
        return Brand::query()->whereKey($brand->getKey())
            ->where('organisation_id', $organisation->getKey())
            ->lockForUpdate()->firstOrFail();
    }

    public function paginate(Organisation $organisation, Brand $brand, int $perPage): LengthAwarePaginator
    {
        return DB::table('brand_memberships as bm')
            ->join('users as u', 'u.id', '=', 'bm.user_id')
            ->join('organisation_memberships as om', function ($join): void {
                $join->on('om.organisation_id', '=', 'bm.organisation_id')
                    ->on('om.user_id', '=', 'bm.user_id');
            })
            ->join('roles as r', 'r.id', '=', 'bm.role_id')
            ->join('roles as org_role', 'org_role.id', '=', 'om.role_id')
            ->where('bm.organisation_id', $organisation->getKey())
            ->where('bm.brand_id', $brand->getKey())
            ->where('r.workspace_id', (string) $organisation->getKey())
            ->where('om.state', StateEnum::ACTIVE->value)
            ->select(['bm.user_id', 'u.name', 'u.email', 'bm.state', 'r.name as role', 'org_role.name as organisation_role'])
            ->orderBy('u.name')->orderBy('u.id')->paginate($perPage);
    }

    public function candidates(Organisation $organisation, Brand $brand, int $limit, string $search = ''): Collection
    {
        return DB::table('organisation_memberships as om')
            ->join('users as u', 'u.id', '=', 'om.user_id')
            ->join('roles as org_role', 'org_role.id', '=', 'om.role_id')
            ->leftJoin('brand_memberships as bm', function ($join) use ($brand): void {
                $join->on('bm.organisation_id', '=', 'om.organisation_id')
                    ->on('bm.user_id', '=', 'om.user_id')
                    ->where('bm.brand_id', '=', $brand->getKey())
                    ->where('bm.state', '=', StateEnum::ACTIVE->value);
            })
            ->where('om.organisation_id', $organisation->getKey())
            ->where('om.state', StateEnum::ACTIVE->value)
            ->whereNull('bm.user_id')
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search): void {
                $inner->where('u.name', 'like', '%'.$search.'%')
                    ->orWhere('u.email', 'like', '%'.$search.'%');
            }))
            ->select(['u.id', 'u.name', 'u.email', 'org_role.name as organisation_role'])
            ->orderBy('u.name')->orderBy('u.id')->limit($limit)->get();
    }

    public function roles(Organisation $organisation): Collection
    {
        return Role::query()->forWorkspace((int) $organisation->getKey())
            ->whereIn('name', ['owner', 'manager', 'contributor', 'viewer'])
            ->whereNull('deleted_at')->orderBy('order')->get();
    }

    public function role(Organisation $organisation, string $name): ?Role
    {
        if (! in_array($name, ['owner', 'manager', 'contributor', 'viewer'], true)) {
            return null;
        }

        return Role::query()->forWorkspace((int) $organisation->getKey())
            ->where('name', $name)->whereNull('deleted_at')->first();
    }

    public function brandRoleHasPermission(Organisation $organisation, int $roleId, string $permission): bool
    {
        return Role::query()->forWorkspace((int) $organisation->getKey())
            ->whereKey($roleId)
            ->whereHas('permissions', fn ($query) => $query->where('permissions.name', $permission))
            ->exists();
    }

    public function findUser(int $id): User
    {
        return User::query()->findOrFail($id);
    }

    public function organisationMember(Organisation $organisation, int $userId): ?object
    {
        return DB::table('organisation_memberships as om')
            ->join('roles as r', 'r.id', '=', 'om.role_id')
            ->where('om.organisation_id', $organisation->getKey())
            ->where('om.user_id', $userId)
            ->where('om.state', StateEnum::ACTIVE->value)
            ->where('r.workspace_id', (string) $organisation->getKey())
            ->select(['om.user_id', 'om.role_id', 'r.name as role_name'])->first();
    }

    public function brandMember(Organisation $organisation, Brand $brand, int $userId): ?object
    {
        return DB::table('brand_memberships as bm')
            ->join('roles as r', 'r.id', '=', 'bm.role_id')
            ->where('bm.organisation_id', $organisation->getKey())
            ->where('bm.brand_id', $brand->getKey())
            ->where('bm.user_id', $userId)
            ->where('r.workspace_id', (string) $organisation->getKey())
            ->select(['bm.user_id', 'bm.role_id', 'bm.state', 'r.name as role_name'])->first();
    }

    public function upsert(Organisation $organisation, Brand $brand, User $user, Role $role): void
    {
        // No Kinship roles are granted here; a brand role is a restriction on the existing org role.
        $query = DB::table('brand_memberships')
            ->where('organisation_id', $organisation->getKey())
            ->where('brand_id', $brand->getKey())
            ->where('user_id', $user->getKey());
        if ($query->exists()) {
            $query->update(['role_id' => $role->getKey(), 'state' => StateEnum::ACTIVE->value,
                'updated_at' => now()]);

            return;
        }
        DB::table('brand_memberships')->insert([
            'organisation_id' => $organisation->getKey(),
            'brand_id' => $brand->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => $role->getKey(),
            'state' => StateEnum::ACTIVE->value,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function revoke(Organisation $organisation, Brand $brand, int $userId): bool
    {
        return DB::table('brand_memberships')
            ->where('organisation_id', $organisation->getKey())
            ->where('brand_id', $brand->getKey())->where('user_id', $userId)
            ->where('state', StateEnum::ACTIVE->value)
            ->update(['state' => StateEnum::REVOKED->value, 'updated_at' => now()]) === 1;
    }
}
