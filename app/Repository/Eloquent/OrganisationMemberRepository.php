<?php

namespace App\Repository\Eloquent;

use App\Enums\StateEnum;
use App\Models\Organisation;
use App\Models\OrganisationInvitation;
use App\Models\User;
use App\Repository\Contracts\OrganisationMemberRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tetranyble\Kinship\Models\Role;

final class OrganisationMemberRepository implements OrganisationMemberRepositoryContract
{
    public function listMembers(Organisation $organisation, int $perPage = 25): LengthAwarePaginator
    {
        return DB::table('organisation_memberships as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->join('roles as r', 'r.id', '=', 'm.role_id')
            ->where('m.organisation_id', $organisation->getKey())
            ->where('r.workspace_id', (string) $organisation->getKey())
            ->select(['u.id', 'u.name', 'u.email', 'm.state', 'm.joined_at', 'r.name as role'])
            ->orderBy('u.name')->orderBy('u.id')->paginate($perPage);
    }

    public function membershipCount(Organisation $organisation): int
    {
        return DB::table('organisation_memberships')
            ->where('organisation_id', $organisation->getKey())->count();
    }

    public function assignableRoles(Organisation $organisation): Collection
    {
        return Role::query()->forWorkspace((int) $organisation->getKey())
            ->whereIn('name', ['owner', 'manager', 'contributor', 'viewer'])
            ->whereNull('deleted_at')
            ->orderBy('order')->get();
    }

    public function activeMemberRoleId(Organisation $organisation, int $userId, bool $lock = false): ?int
    {
        $query = DB::table('organisation_memberships')
            ->where('organisation_id', $organisation->getKey())
            ->where('user_id', $userId)
            ->where('state', StateEnum::ACTIVE->value);

        if ($lock) {
            $query->lockForUpdate();
        }

        $id = $query->value('role_id');

        return $id === null ? null : (int) $id;
    }

    public function activeOwnerCount(Organisation $organisation): int
    {
        return DB::table('organisation_memberships as m')
            ->join('roles as r', 'r.id', '=', 'm.role_id')
            ->where('m.organisation_id', $organisation->getKey())
            ->where('m.state', StateEnum::ACTIVE->value)
            ->where('r.workspace_id', (string) $organisation->getKey())
            ->where('r.name', 'owner')->count();
    }

    public function findWorkspaceRole(Organisation $organisation, string $name): ?Role
    {
        if (! in_array($name, ['owner', 'manager', 'contributor', 'viewer'], true)) {
            return null;
        }

        return Role::query()->forWorkspace((int) $organisation->getKey())
            ->where('name', $name)->whereNull('deleted_at')->first();
    }

    public function lockedOrganisation(Organisation $organisation): Organisation
    {
        return Organisation::query()->whereKey($organisation->getKey())->lockForUpdate()->firstOrFail();
    }

    public function findUserByEmail(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }

    public function findUserById(int $userId): User
    {
        return User::query()->findOrFail($userId);
    }

    public function findWorkspaceRoleById(Organisation $organisation, int $roleId): ?Role
    {
        return Role::query()->forWorkspace((int) $organisation->getKey())
            ->whereKey($roleId)->whereNull('deleted_at')->first();
    }

    public function activateAcceptedInvitation(Organisation $organisation, User $user, OrganisationInvitation $invitation): void
    {
        $now = now();
        DB::table('organisation_memberships')->updateOrInsert(
            ['organisation_id' => $organisation->getKey(), 'user_id' => $user->getKey()],
            ['role_id' => $invitation->role_id, 'state' => StateEnum::ACTIVE->value,
                'invited_by_user_id' => $invitation->invited_by_user_id,
                'joined_at' => $now, 'updated_at' => $now]
        );
    }

    public function revokeBrandMemberships(Organisation $organisation, int $userId): void
    {
        DB::table('brand_memberships')
            ->where('organisation_id', $organisation->getKey())
            ->where('user_id', $userId)
            ->update(['state' => StateEnum::REVOKED->value, 'updated_at' => now()]);
    }
}
