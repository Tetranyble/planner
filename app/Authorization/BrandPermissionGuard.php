<?php

namespace App\Authorization;

use App\Enums\StateEnum;
use App\Models\Brand;
use App\Models\User;
use App\Repository\Contracts\BrandMemberRepositoryContract;

/**
 * Brand authorization = active workspace context ∩ Kinship organisation permission
 *     ∩ assigned brand-role permission.
 * Organisation-owner recovery is allowed ONLY for brand membership administration.
 * Controllers/agents must call this for brand-scoped actions; never check org Kinship alone.
 */
final class BrandPermissionGuard
{
    public function __construct(
        private readonly CurrentOrganisation $context,
        private readonly BrandMemberRepositoryContract $members,
    ) {}

    public function allows(User $actor, Brand $brand, string $permission, bool $ownerRecovery = false): bool
    {
        $organisation = $brand->organisation;
        if (! $actor->exists || $organisation === null || ! $brand->isActive() || ! $organisation->isActive()
            || $this->context->idFor($actor) !== (int) $organisation->getKey()
            || ! $actor->hasPermission($permission)) {
            return false;
        }
        $orgMembership = $this->members->organisationMember($organisation, (int) $actor->getKey());
        if ($orgMembership === null) {
            return false;
        }
        if ($ownerRecovery && str_starts_with($permission, 'brand_membership.')
            && $orgMembership->role_name === 'owner') {
            return true;
        }

        $brandMembership = $this->members->brandMember($organisation, $brand, (int) $actor->getKey());
        if ($brandMembership === null || $brandMembership->state !== StateEnum::ACTIVE->value) {
            return false;
        }

        return $this->members->brandRoleHasPermission($organisation,
            (int) $brandMembership->role_id, $permission);
    }
}
