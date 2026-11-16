<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;

interface BrandWriteRepositoryContract
{
    /** Must be called inside an open transaction; locks the membership against concurrent revocation. */
    public function activeRoleIdFor(Organisation $organisation, User $member): ?int;

    /** Create one brand scoped to the organisation, and establish the creator's own brand membership. */
    public function createForMember(
        Organisation $organisation,
        User $creator,
        int $organisationRoleId,
        string $name,
        ?string $websiteUrl,
        string $timezone,
    ): Brand;
}
