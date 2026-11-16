<?php

namespace App\Services\Onboarding;

use App\Authorization\CurrentOrganisation;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\BrandWriteRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/** Organization-scoped, permission-checked brand creation with creator membership. */
final class CreateBrandService
{
    public function __construct(
        private readonly CurrentOrganisation $context,
        private readonly BrandWriteRepositoryContract $brands,
        private readonly AuditEventRepositoryContract $audit,
    ) {}

    public function canCreate(User $actor, Organisation $organisation): bool
    {
        return $actor->exists
            && $organisation->isActive()
            && $this->context->idFor($actor) === (int) $organisation->getKey()
            && $actor->hasPermission('brand.store');
    }

    public function execute(
        User $actor,
        Organisation $organisation,
        string $name,
        ?string $websiteUrl,
        ?string $timezone,
    ): Brand {
        if (! $this->canCreate($actor, $organisation)) {
            throw new AuthorizationException('Not permitted to create a brand in this organisation.');
        }

        return DB::transaction(function () use ($actor, $organisation, $name, $websiteUrl, $timezone): Brand {
            // Acquire the actual membership role under lock; never accept a role or tenant ID from HTTP.
            $roleId = $this->brands->activeRoleIdFor($organisation, $actor);
            if ($roleId === null || ! $this->canCreate($actor, $organisation)) {
                throw new AuthorizationException('Organisation access was revoked.');
            }

            $brand = $this->brands->createForMember(
                $organisation,
                $actor,
                $roleId,
                $name,
                $websiteUrl,
                $timezone ?? ($organisation->timezone instanceof \BackedEnum
                    ? $organisation->timezone->value : $organisation->timezone),
            );

            $this->audit->record(
                $organisation, $actor, 'brand.created', 'brand', (string) $brand->getKey(), $brand,
            );

            return $brand;
        }, attempts: 3);
    }
}
