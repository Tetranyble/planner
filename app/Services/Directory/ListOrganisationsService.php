<?php

namespace App\Services\Directory;

use App\Models\User;
use App\Repository\Contracts\OrganisationDirectoryRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/** Active membership is enough to discover an organisation; permissions are checked after selection. */
final class ListOrganisationsService
{
    public function __construct(private readonly OrganisationDirectoryRepositoryContract $organisations) {}

    public function execute(User $actor, int $perPage = 20): LengthAwarePaginator
    {
        if (! $actor->exists) {
            throw new InvalidArgumentException('An existing user is required.');
        }

        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Page size must be between 1 and 100.');
        }

        return $this->organisations->paginateActiveForMember($actor, $perPage);
    }
}
