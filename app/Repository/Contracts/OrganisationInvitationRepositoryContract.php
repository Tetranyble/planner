<?php

namespace App\Repository\Contracts;

use App\Models\Organisation;
use App\Models\OrganisationInvitation;
use App\Models\User;
use Illuminate\Support\Collection;
use Tetranyble\Kinship\Models\Role;

interface OrganisationInvitationRepositoryContract
{
    public function retireOutstanding(Organisation $organisation, string $email): void;

    public function create(Organisation $organisation, User $inviter, Role $role, string $email, string $tokenHash): OrganisationInvitation;

    public function byTokenHash(string $tokenHash): ?OrganisationInvitation;

    public function markAccepted(OrganisationInvitation $invitation): void;

    public function cancelByUuid(Organisation $organisation, string $uuid): ?OrganisationInvitation;

    public function byTokenHashForUpdate(string $tokenHash): ?OrganisationInvitation;

    /** @return Collection<int, OrganisationInvitation> */
    public function pendingForOrganisation(Organisation $organisation): Collection;
}
