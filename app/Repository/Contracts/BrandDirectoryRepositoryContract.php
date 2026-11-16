<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Brand visibility requires an active membership in the same organisation. */
interface BrandDirectoryRepositoryContract
{
    /** @return LengthAwarePaginator<Brand> */
    public function paginateActiveForMember(User $actor, int $organisationId, int $perPage): LengthAwarePaginator;
}
