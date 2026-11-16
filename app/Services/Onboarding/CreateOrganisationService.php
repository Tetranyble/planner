<?php

namespace App\Services\Onboarding;

use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\OrganisationWriteRepositoryContract;
use App\Services\Membership\OrganisationMembershipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Tetranyble\Kinship\Catalog\PermissionCatalogSeeder;
use Tetranyble\Kinship\Models\Role;

/** Atomic creation of an active workspace, its default preferences and its first owner. */
final class CreateOrganisationService
{
    public function __construct(
        private readonly OrganisationWriteRepositoryContract $organisations,
        private readonly PermissionCatalogSeeder $kinshipCatalog,
        private readonly OrganisationMembershipService $memberships,
        private readonly AuditEventRepositoryContract $audit,
    ) {}

    public function execute(User $actor, string $name, string $timezone): Organisation
    {
        if (! $actor->exists) {
            throw new AuthorizationException('An authenticated, persisted user is required.');
        }

        return DB::transaction(function () use ($actor, $name, $timezone): Organisation {
            $organisation = $this->organisations->createForOwner($actor, $name, $timezone);
            $this->organisations->initializePreferences($organisation);

            // Do not recreate package-owned roles, permissions or pivots.
            // Kinship itself owns these tables and the configured catalog.
            $this->kinshipCatalog->seed(
                (int) $organisation->getKey(),
                workspaceScoped: true,
            );

            $ownerRole = Role::query()
                ->forWorkspace((int) $organisation->getKey())
                ->where('name', 'owner')
                ->firstOrFail();

            // Existing service only permits the owner-created first membership to bootstrap.
            $this->memberships->grant($organisation, $actor, $ownerRole);

            $this->audit->record(
                $organisation, $actor, 'organisation.created', 'organisation', (string) $organisation->getKey(),
            );

            return $organisation;
        }, attempts: 3);
    }
}
