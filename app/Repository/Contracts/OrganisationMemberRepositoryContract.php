<?php

namespace App\Repository\Contracts;

use App\Models\Organisation;
use App\Models\OrganisationInvitation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tetranyble\Kinship\Models\Role;

interface OrganisationMemberRepositoryContract
{
    /** @return LengthAwarePaginator<int, object> */
    public function listMembers(Organisation $organisation, int $perPage = 25): LengthAwarePaginator;

    public function membershipCount(Organisation $organisation): int;

    /** @return Collection<int, Role> */
    public function assignableRoles(Organisation $organisation): Collection;

    public function activeMemberRoleId(Organisation $organisation, int $userId, bool $lock = false): ?int;

    public function activeOwnerCount(Organisation $organisation): int;

    public function findWorkspaceRole(Organisation $organisation, string $name): ?Role;

    public function lockedOrganisation(Organisation $organisation): Organisation;

    public function findUserByEmail(string $email): ?User;

    public function findUserById(int $userId): User;

    public function findWorkspaceRoleById(Organisation $organisation, int $roleId): ?Role;

    public function activateAcceptedInvitation(Organisation $organisation, User $user, OrganisationInvitation $invitation): void;

    public function revokeBrandMemberships(Organisation $organisation, int $userId): void;
}
