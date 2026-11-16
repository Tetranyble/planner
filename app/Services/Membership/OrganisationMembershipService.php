<?php

namespace App\Services\Membership;

use App\Authorization\CurrentOrganisation;
use App\Domain\Membership\OwnerQuorum;
use App\Enums\StateEnum;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\OrganisationMemberRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Tetranyble\Kinship\Models\Role;

/** Maintains the database membership and the Kinship workspace grant as one transaction. */
final class OrganisationMembershipService
{
    public function __construct(private readonly OrganisationMemberRepositoryContract $members) {}

    public function grant(Organisation $organisation, User $user, Role $role, StateEnum $state = StateEnum::ACTIVE): void
    {
        $this->assertRoleIsWorkspaceBound($organisation, $role);
        DB::transaction(function () use ($organisation, $user, $role, $state): void {
            $this->members->lockedOrganisation($organisation);
            $this->authorizeMembershipChange($organisation, $user);
            $this->guardOwnerTransition($organisation, $user, $role->name);
            $now = now();
            $existingJoinedAt = DB::table('organisation_memberships')
                ->where('organisation_id', $organisation->getKey())
                ->where('user_id', $user->getKey())->value('joined_at');
            DB::table('organisation_memberships')->updateOrInsert(
                ['organisation_id' => $organisation->getKey(), 'user_id' => $user->getKey()],
                ['role_id' => $role->getKey(), 'state' => $state->value,
                    'joined_at' => $state === StateEnum::ACTIVE ? ($existingJoinedAt ?? $now) : null, 'updated_at' => $now]
            );

            // Grant only inside the exact Kinship workspace and never cross-tenant.
            // Suspended/revoked membership can no longer resolve a workspace;
            // stored Kinship grants therefore remain inaccessible, but are revoked
            // explicitly by the membership revocation workflow.
            if ($state === StateEnum::ACTIVE) {
                app(CurrentOrganisation::class)->within($user, (int) $organisation->getKey(), function () use ($user, $role): void {
                    $user->syncRoles($role);
                });
            }
        });
    }

    public function revoke(Organisation $organisation, User $user): void
    {
        DB::transaction(function () use ($organisation, $user): void {
            $this->members->lockedOrganisation($organisation);
            $this->authorizeMembershipChange($organisation, $user);
            $this->guardOwnerTransition($organisation, $user, null);
            // Revoke Kinship roles *before* the membership goes inactive so the
            // scoped resolver can still reach the correct workspace.
            app(CurrentOrganisation::class)->within($user, (int) $organisation->getKey(), function () use ($user): void {
                $user->syncRoles();
            });

            DB::table('organisation_memberships')
                ->where('organisation_id', $organisation->getKey())
                ->where('user_id', $user->getKey())
                ->update(['state' => StateEnum::REVOKED->value, 'updated_at' => now()]);
            $this->members->revokeBrandMemberships($organisation, (int) $user->getKey());
        });
    }

    private function guardOwnerTransition(Organisation $organisation, User $target, ?string $newRole): void
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException('Authenticated actor required.');
        }
        $currentId = $this->members->activeMemberRoleId($organisation, (int) $target->getKey(), true);
        $oldRole = $currentId !== null ? $this->members->findWorkspaceRoleById($organisation, $currentId) : null;
        $owner = $this->members->findWorkspaceRole($organisation, 'owner');
        $actorRole = $this->members->activeMemberRoleId($organisation, (int) $actor->getKey());
        $bootstrap = $this->members->membershipCount($organisation) === 0
            && $actor->is($target) && (int) $organisation->created_by_user_id === (int) $actor->getKey();

        if ($bootstrap) {
            if ($newRole !== 'owner') {
                throw new AuthorizationException('The first organisation membership must be its owner.');
            }

            return;
        }
        if ($newRole === 'owner' || $oldRole?->name === 'owner') {
            if ($owner === null || $actorRole !== (int) $owner->getKey()) {
                throw new AuthorizationException('Only an active owner can modify an owner membership.');
            }
        }
        OwnerQuorum::ensureSurvives(
            $oldRole?->name === 'owner', $newRole !== 'owner', $this->members->activeOwnerCount($organisation),
        );
    }

    private function authorizeMembershipChange(Organisation $organisation, User $subject): void
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException('An authenticated actor is required.');
        }

        $existingCount = DB::table('organisation_memberships')
            ->where('organisation_id', $organisation->getKey())->count();

        // Only a creator provisioning their *own* first membership may bootstrap.
        // Restrict that exception at the actual organisation-creation endpoint too.
        if ($existingCount === 0
            && $actor->is($subject)
            && (int) $organisation->created_by_user_id === (int) $actor->getKey()
            && $organisation->created_by_user_id !== null) {
            return;
        }

        if (app(CurrentOrganisation::class)->idFor($actor) !== (int) $organisation->getKey()
            || ! $actor->hasPermission('organisation_membership.update')) {
            throw new AuthorizationException('Not permitted to manage organisation members.');
        }
    }

    public function assertRoleIsWorkspaceBound(Organisation $organisation, Role $role): void
    {
        if ((string) $role->getAttribute('workspace_id') !== (string) $organisation->getKey()
            || $role->trashed()) {
            throw new AuthorizationException('Role does not belong to the selected organisation.');
        }
    }
}
