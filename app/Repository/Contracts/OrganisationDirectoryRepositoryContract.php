<?php

namespace App\Repository\Contracts;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Read-only organisation discovery. Never exposes an unscoped read. */
interface OrganisationDirectoryRepositoryContract
{
    /** @return LengthAwarePaginator<Organisation> */
    public function paginateActiveForMember(User $actor, int $perPage): LengthAwarePaginator;
}
