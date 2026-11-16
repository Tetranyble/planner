<?php

namespace App\Services\Membership;

use App\Authorization\CurrentOrganisation;
use App\Domain\Membership\InvitationToken;
use App\Domain\Membership\OwnerQuorum;
use App\Models\Organisation;
use App\Models\OrganisationInvitation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\OrganisationInvitationOutboxRepositoryContract;
use App\Repository\Contracts\OrganisationInvitationRepositoryContract;
use App\Repository\Contracts\OrganisationMemberRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/** Transactional membership lifecycle; shared by web, REST and future AI tool adapters. */
final class ManageOrganisationMembers
{
    public function __construct(
        private readonly CurrentOrganisation $context,
        private readonly OrganisationMemberRepositoryContract $members,
        private readonly OrganisationInvitationRepositoryContract $invitations,
        private readonly OrganisationInvitationOutboxRepositoryContract $outbox,
        private readonly OrganisationMembershipService $grants,
        private readonly AuditEventRepositoryContract $audit,
    ) {}

    public function canManage(User $actor, Organisation $organisation, string $permission = 'organisation_membership.index'): bool
    {
        return $actor->exists && $organisation->isActive()
            && $this->context->idFor($actor) === (int) $organisation->getKey()
            && $actor->hasPermission($permission);
    }

    public function directory(User $actor, Organisation $organisation, int $perPage = 25): array
    {
        $this->assertManager($actor, $organisation, 'organisation_membership.index');
        $perPage = min(100, max(1, $perPage));
        $page = $this->members->listMembers($organisation, $perPage);
        $actorRoleId = $this->members->activeMemberRoleId($organisation, (int) $actor->getKey());
        $ownerRole = $this->members->findWorkspaceRole($organisation, 'owner');
        $canManageOwners = $ownerRole !== null && $actorRoleId === (int) $ownerRole->getKey();

        return [
            'can_manage_owners' => $canManageOwners,
            'members' => $page->getCollection()->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'role' => $row->role,
                'state' => $row->state,
                'joined_at' => $row->joined_at,
            ])->all(),
            'members_page' => [
                'page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'total' => $page->total(), 'per_page' => $page->perPage(),
            ],
            'roles' => $this->members->assignableRoles($organisation)
                ->filter(fn ($r) => $canManageOwners || $r->name !== 'owner')
                ->values()->map(fn ($r) => [
                    'name' => $r->name, 'label' => $r->label ?: ucfirst($r->name),
                ])->all(),
            'invitations' => $this->invitations->pendingForOrganisation($organisation)->map(fn ($invitation) => [
                'uuid' => $invitation->uuid,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'delivery_state' => $invitation->delivery_state ?? 'PENDING',
            ])->all(),
        ];
    }

    public function invite(User $actor, Organisation $organisation, string $email, string $roleName): OrganisationInvitation
    {
        $this->assertManager($actor, $organisation, 'organisation_invitation.store');
        $email = mb_strtolower(trim($email));

        return DB::transaction(function () use ($actor, $organisation, $email, $roleName): OrganisationInvitation {
            // Lock the organisation to serialize invitations with acceptance and membership changes.
            $this->lockActiveOrganisation($organisation);
            $this->assertManager($actor, $organisation, 'organisation_invitation.store');
            $role = $this->members->findWorkspaceRole($organisation, $roleName);
            abort_if($role === null, 422, 'Invalid organisation role.');
            $this->authorizeRoleAssignment($actor, $organisation, $role->name);

            $existing = $this->members->findUserByEmail($email);
            if ($existing !== null && $this->members->activeMemberRoleId($organisation, (int) $existing->getKey()) !== null) {
                abort(409, 'This user is already an active organisation member.');
            }

            $this->invitations->retireOutstanding($organisation, $email);
            $token = InvitationToken::issue();
            $invitation = $this->invitations->create($organisation, $actor, $role, $email, InvitationToken::digest($token));

            // Durable outbox owns delivery; the raw token is encrypted at rest.
            $this->outbox->queue($invitation, $token);
            $this->audit->record($organisation, $actor, 'membership.invited', 'organisation_invitation', (string) $invitation->getKey(), metadata: ['role' => $roleName]);

            return $invitation;
        }, attempts: 3);
    }

    public function accept(User $actor, string $token): Organisation
    {
        if (! $actor->exists || ! $actor->hasVerifiedEmail()) {
            throw new AuthorizationException('A verified account is required.');
        }
        if (! InvitationToken::hasValidFormat($token)) {
            abort(404);
        }

        return DB::transaction(function () use ($actor, $token): Organisation {
            $hash = InvitationToken::digest($token);
            $lookup = $this->invitations->byTokenHash($hash);
            abort_if($lookup === null, 404);

            $organisation = $this->members->lockedOrganisation($lookup->organisation);
            abort_unless($organisation->isActive(), 409, 'Organisation is not active.');
            $invitation = $this->invitations->byTokenHashForUpdate($hash);
            abort_if($invitation === null || $invitation->accepted_at !== null || $invitation->revoked_at !== null
                || $invitation->expires_at->isPast(), 410, 'Invitation is no longer valid.');
            abort_unless(hash_equals($invitation->email, mb_strtolower($actor->email)), 403, 'Invitation is for another account.');

            $role = $this->members->findWorkspaceRoleById($organisation, (int) $invitation->role_id);
            abort_if($role === null, 409, 'Invitation role is unavailable.');
            abort_if($this->members->activeMemberRoleId($organisation, (int) $actor->getKey()) !== null, 409, 'Already a member.');

            $this->members->activateAcceptedInvitation($organisation, $actor, $invitation);
            $this->context->within($actor, (int) $organisation->getKey(), function () use ($actor, $role): void {
                $actor->syncRoles($role);
            });
            $this->invitations->markAccepted($invitation);
            $this->audit->record($organisation, $actor, 'membership.invitation_accepted', 'organisation_membership', (string) $actor->getKey());

            return $organisation;
        }, attempts: 3);
    }

    public function changeRole(User $actor, Organisation $organisation, int $memberUserId, string $roleName): void
    {
        $this->assertManager($actor, $organisation, 'organisation_membership.update');
        DB::transaction(function () use ($actor, $organisation, $memberUserId, $roleName): void {
            $this->lockActiveOrganisation($organisation);
            $this->assertManager($actor, $organisation, 'organisation_membership.update');
            $target = $this->members->findUserById($memberUserId);
            $oldRoleId = $this->members->activeMemberRoleId($organisation, $memberUserId, true);
            abort_if($oldRoleId === null, 404, 'No active member in organisation.');
            $currentRole = $this->members->findWorkspaceRoleById($organisation, $oldRoleId);
            abort_if($currentRole === null, 409, 'Member role is unavailable.');
            $newRole = $this->members->findWorkspaceRole($organisation, $roleName);
            abort_if($newRole === null, 422, 'Unknown role.');
            $this->authorizeRoleAssignment($actor, $organisation, $newRole->name);
            if ($currentRole->name === 'owner') {
                $this->authorizeRoleAssignment($actor, $organisation, 'owner');
                $this->protectLastOwner($organisation, $newRole->name !== 'owner');
            }
            if ((int) $oldRoleId === (int) $newRole->getKey()) {
                return;
            }
            $this->grants->grant($organisation, $target, $newRole);
            $this->audit->record($organisation, $actor, 'membership.role_changed', 'organisation_membership', (string) $target->getKey(), metadata: ['old_role' => $currentRole->name, 'new_role' => $newRole->name]);
        }, attempts: 3);
    }

    public function revoke(User $actor, Organisation $organisation, int $memberUserId): void
    {
        $this->assertManager($actor, $organisation, 'organisation_membership.delete');
        DB::transaction(function () use ($actor, $organisation, $memberUserId): void {
            $this->lockActiveOrganisation($organisation);
            $this->assertManager($actor, $organisation, 'organisation_membership.delete');
            $target = $this->members->findUserById($memberUserId);
            $roleId = $this->members->activeMemberRoleId($organisation, $memberUserId, true);
            abort_if($roleId === null, 404, 'No active member in organisation.');
            $role = $this->members->findWorkspaceRoleById($organisation, $roleId);
            abort_if($role === null, 409, 'Member role is unavailable.');
            if ($role->name === 'owner') {
                $this->authorizeRoleAssignment($actor, $organisation, 'owner');
                $this->protectLastOwner($organisation, true);
            }
            $this->grants->revoke($organisation, $target);
            $this->audit->record($organisation, $actor, 'membership.revoked', 'organisation_membership', (string) $target->getKey());
        }, attempts: 3);
    }

    public function cancelInvitation(User $actor, Organisation $organisation, string $uuid): void
    {
        $this->assertManager($actor, $organisation, 'organisation_invitation.delete');
        DB::transaction(function () use ($actor, $organisation, $uuid): void {
            $this->lockActiveOrganisation($organisation);
            $this->assertManager($actor, $organisation, 'organisation_invitation.delete');
            $invitation = $this->invitations->cancelByUuid($organisation, $uuid);
            abort_if($invitation === null, 404);
            abort_if($invitation->accepted_at !== null, 409, 'An accepted invitation cannot be cancelled.');
            if ($invitation->wasChanged('revoked_at')) {
                $this->audit->record($organisation, $actor, 'membership.invitation_revoked', 'organisation_invitation', (string) $invitation->getKey());
            }
        }, attempts: 3);
    }

    private function lockActiveOrganisation(Organisation $organisation): void
    {
        $record = $this->members->lockedOrganisation($organisation);
        abort_unless($record->isActive(), 409, 'Organisation is not active.');
    }

    private function assertManager(User $actor, Organisation $organisation, string $permission): void
    {
        if (! $this->canManage($actor, $organisation, $permission)) {
            throw new AuthorizationException('Not permitted to manage organisation membership.');
        }
    }

    private function authorizeRoleAssignment(User $actor, Organisation $organisation, string $role): void
    {
        if ($role !== 'owner') {
            return;
        }
        $id = $this->members->activeMemberRoleId($organisation, (int) $actor->getKey());
        $owner = $this->members->findWorkspaceRole($organisation, 'owner');
        if ($owner === null || $id !== (int) $owner->getKey()) {
            throw new AuthorizationException('Only an organisation owner can assign or change owner roles.');
        }
    }

    private function protectLastOwner(Organisation $organisation, bool $losingOwner): void
    {
        OwnerQuorum::ensureSurvives(true, $losingOwner, $this->members->activeOwnerCount($organisation));
    }
}
