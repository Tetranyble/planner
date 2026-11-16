<?php

namespace App\Services\Membership;

use App\Authorization\BrandPermissionGuard;
use App\Authorization\CurrentOrganisation;
use App\Domain\Membership\BrandRoleHierarchy;
use App\Enums\StateEnum;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\BrandMemberRepositoryContract;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Org-scoped Kinship permissions AND brand-specific membership, with owner recovery. */
final class ManageBrandMembers
{
    public function __construct(
        private readonly CurrentOrganisation $context,
        private readonly BrandPermissionGuard $permissionGuard,
        private readonly BrandMemberRepositoryContract $members,
        private readonly AuditEventRepositoryContract $audit,
    ) {}

    public function canManage(User $actor, Organisation $organisation, Brand $brand, string $permission = 'brand_membership.index'): bool
    {
        return (int) $brand->organisation_id === (int) $organisation->getKey()
            && $this->permissionGuard->allows($actor, $brand, $permission, ownerRecovery: true);
    }

    public function directory(User $actor, Organisation $organisation, Brand $brand, int $perPage = 25, string $candidateSearch = ''): array
    {
        $this->assertManager($actor, $organisation, $brand, 'brand_membership.index');
        $perPage = max(1, min(100, $perPage));
        $candidateSearch = mb_substr(trim($candidateSearch), 0, 100);
        $page = $this->members->paginate($organisation, $brand, $perPage);
        $actorRole = $this->members->organisationMember($organisation, (int) $actor->getKey());
        $isOwner = $actorRole?->role_name === 'owner';

        return [
            'members' => $page->getCollection()->map(fn (object $member): array => [
                'id' => (int) $member->user_id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role,
                'organisation_role' => $member->organisation_role,
                'state' => $member->state,
            ])->values()->all(),
            'pagination' => [
                'page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'total' => $page->total(), 'per_page' => $page->perPage(),
            ],
            'roles' => $this->members->roles($organisation)
                ->filter(fn ($role): bool => $isOwner || $role->name !== 'owner')
                ->map(fn ($role): array => ['name' => $role->name, 'label' => $role->label ?: ucfirst($role->name)])
                ->values()->all(),
            'candidate_search' => $candidateSearch,
            'candidates' => $this->members->candidates($organisation, $brand, 50, $candidateSearch)
                ->map(fn (object $member): array => [
                    'id' => (int) $member->id, 'name' => $member->name, 'email' => $member->email,
                    'organisation_role' => $member->organisation_role,
                ])->values()->all(),
            'can_manage_owners' => $isOwner,
        ];
    }

    public function add(User $actor, Organisation $organisation, Brand $brand, int $userId, string $roleName): void
    {
        $this->assertManager($actor, $organisation, $brand, 'brand_membership.store');
        DB::transaction(function () use ($actor, $organisation, $brand, $userId, $roleName): void {
            $this->lockAndAuthorize($actor, $organisation, $brand, 'brand_membership.store');
            $targetMembership = $this->requireOrganisationMember($organisation, $userId);
            $this->assertRoleAssignable($actor, $organisation, $roleName, $targetMembership->role_name);
            $existing = $this->members->brandMember($organisation, $brand, $userId);
            abort_if($existing !== null && $existing->state === StateEnum::ACTIVE->value,
                409, 'Member already has active brand access. Change their role instead.');
            $role = $this->members->role($organisation, $roleName);
            abort_if($role === null, 422, 'Invalid role for organisation.');
            $target = $this->members->findUser($userId);
            $this->members->upsert($organisation, $brand, $target, $role);
            $this->audit->record($organisation, $actor, 'brand.membership_added', 'brand_membership',
                (string) $userId, $brand, ['role' => $roleName]);
        }, attempts: 3);
    }

    public function changeRole(User $actor, Organisation $organisation, Brand $brand, int $userId, string $roleName): void
    {
        $this->assertManager($actor, $organisation, $brand, 'brand_membership.update');
        DB::transaction(function () use ($actor, $organisation, $brand, $userId, $roleName): void {
            $this->lockAndAuthorize($actor, $organisation, $brand, 'brand_membership.update');
            $targetMembership = $this->requireOrganisationMember($organisation, $userId);
            $existing = $this->members->brandMember($organisation, $brand, $userId);
            abort_if($existing === null || $existing->state !== StateEnum::ACTIVE->value,
                404, 'No active brand membership.');
            $this->authorizeOwnerModification($actor, $organisation, $existing->role_name);
            $this->assertRoleAssignable($actor, $organisation, $roleName, $targetMembership->role_name);
            $role = $this->members->role($organisation, $roleName);
            abort_if($role === null, 422, 'Invalid role for organisation.');
            if ($existing->role_id === (int) $role->getKey()) {
                return;
            }
            $this->members->upsert($organisation, $brand, $this->members->findUser($userId), $role);
            $this->audit->record($organisation, $actor, 'brand.membership_role_changed', 'brand_membership',
                (string) $userId, $brand, ['previous_role' => $existing->role_name, 'role' => $roleName]);
        }, attempts: 3);
    }

    public function revoke(User $actor, Organisation $organisation, Brand $brand, int $userId): void
    {
        $this->assertManager($actor, $organisation, $brand, 'brand_membership.delete');
        DB::transaction(function () use ($actor, $organisation, $brand, $userId): void {
            $this->lockAndAuthorize($actor, $organisation, $brand, 'brand_membership.delete');
            $current = $this->members->brandMember($organisation, $brand, $userId);
            abort_if($current === null || $current->state !== StateEnum::ACTIVE->value, 404);
            $this->authorizeOwnerModification($actor, $organisation, $current->role_name);
            abort_unless($this->members->revoke($organisation, $brand, $userId), 409);
            $this->audit->record($organisation, $actor, 'brand.membership_revoked', 'brand_membership',
                (string) $userId, $brand);
        }, attempts: 3);
    }

    private function lockAndAuthorize(User $actor, Organisation $organisation, Brand $brand, string $permission): void
    {
        // Lock order is organisation first, then brand; org revocation uses same first lock.
        $this->members->lockOrganisation($organisation);
        $this->members->lockBrand($organisation, $brand);
        $this->assertManager($actor, $organisation, $brand, $permission);
    }

    private function requireOrganisationMember(Organisation $organisation, int $userId): object
    {
        $member = $this->members->organisationMember($organisation, $userId);
        abort_if($member === null, 422, 'User must be an active organisation member.');

        return $member;
    }

    private function assertRoleAssignable(User $actor, Organisation $organisation, string $roleName, string $targetOrgRole): void
    {
        $actorOrgRole = $this->requireOrganisationMember($organisation, (int) $actor->getKey())->role_name;
        if ($roleName === 'owner' && $actorOrgRole !== 'owner') {
            throw new AuthorizationException('Only an organisation owner may assign a brand owner.');
        }
        try {
            BrandRoleHierarchy::assertAssignable($targetOrgRole, $roleName);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['role' => [$exception->getMessage()]]);
        }
    }

    private function authorizeOwnerModification(User $actor, Organisation $organisation, string $currentRole): void
    {
        if ($currentRole === 'owner'
            && $this->requireOrganisationMember($organisation, (int) $actor->getKey())->role_name !== 'owner') {
            throw new AuthorizationException('Only an organisation owner can change a brand owner.');
        }
    }

    private function assertManager(User $actor, Organisation $organisation, Brand $brand, string $permission): void
    {
        if (! $this->canManage($actor, $organisation, $brand, $permission)) {
            throw new AuthorizationException('Not permitted to manage members of this brand.');
        }
    }
}
