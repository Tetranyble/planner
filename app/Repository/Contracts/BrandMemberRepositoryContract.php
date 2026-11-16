<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tetranyble\Kinship\Models\Role;

interface BrandMemberRepositoryContract
{
    public function lockOrganisation(Organisation $organisation): Organisation;

    public function lockBrand(Organisation $organisation, Brand $brand): Brand;

    /** @return LengthAwarePaginator<int, object> */
    public function paginate(Organisation $organisation, Brand $brand, int $perPage): LengthAwarePaginator;

    /** @return Collection<int, object> */
    public function candidates(Organisation $organisation, Brand $brand, int $limit, string $search = ''): Collection;

    /** @return Collection<int, Role> */
    public function roles(Organisation $organisation): Collection;

    public function role(Organisation $organisation, string $name): ?Role;

    public function brandRoleHasPermission(Organisation $organisation, int $roleId, string $permission): bool;

    public function findUser(int $id): User;

    /** @return object|null Active organisation membership with role_name, role_id */
    public function organisationMember(Organisation $organisation, int $userId): ?object;

    /** @return object|null Active or inactive brand membership with role_name, role_id, state */
    public function brandMember(Organisation $organisation, Brand $brand, int $userId): ?object;

    public function upsert(Organisation $organisation, Brand $brand, User $user, Role $role): void;

    public function revoke(Organisation $organisation, Brand $brand, int $userId): bool;
}
