<?php

namespace App\Repository\Contracts;

use App\Models\OrganisationInvitation;

interface OrganisationInvitationOutboxRepositoryContract
{
    /** Store encrypted delivery instructions transactionally with invitation creation. */
    public function queue(OrganisationInvitation $invitation, string $token): void;
}
