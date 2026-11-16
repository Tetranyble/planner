<?php

namespace App\Services\Membership;

use App\Authorization\CurrentOrganisation;
use App\Enums\StateEnum;
use App\Models\Brand;
use App\Models\User;
use App\Repository\Contracts\BrandMemberRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use LogicException;
use Tetranyble\Kinship\Models\Role;

/**
 * Compatibility layer for older callers. All writes now use the audited domain service.
 * New code should inject ManageBrandMembers directly.
 */
final class BrandMembershipService
{
    public function __construct(
        private readonly BrandMemberRepositoryContract $members,
        private readonly CurrentOrganisation $context,
        private readonly ManageBrandMembers $manager,
    ) {}

    public function grant(Brand $brand, User $user, Role $role, StateEnum $state = StateEnum::ACTIVE): void
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException('Authenticated brand administrator required.');
        }
        if ($state === StateEnum::SUSPENDED || $state === StateEnum::INVITED) {
            throw new LogicException('Suspension/invitation must use an explicit audited membership transition.');
        }
        $organisation = $brand->organisation;
        app(OrganisationMembershipService::class)->assertRoleIsWorkspaceBound($organisation, $role);
        $existing = $this->members->brandMember($organisation, $brand, (int) $user->getKey());
        if ($state === StateEnum::REVOKED) {
            $this->manager->revoke($actor, $organisation, $brand, (int) $user->getKey());

            return;
        }
        if ($existing !== null && $existing->state === StateEnum::ACTIVE->value) {
            $this->manager->changeRole($actor, $organisation, $brand, (int) $user->getKey(), $role->name);

            return;
        }
        $this->manager->add($actor, $organisation, $brand, (int) $user->getKey(), $role->name);
    }

    public function canAccess(User $user, Brand $brand): bool
    {
        if (! $brand->isActive() || ! $brand->organisation->isActive()
            || $this->context->idFor($user) !== (int) $brand->organisation_id) {
            return false;
        }
        $organisation = $brand->organisation;

        return $this->members->organisationMember($organisation, (int) $user->getKey()) !== null
            && ($membership = $this->members->brandMember($organisation, $brand, (int) $user->getKey())) !== null
            && $membership->state === StateEnum::ACTIVE->value;
    }
}
