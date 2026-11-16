<?php

namespace App\Services\Directory;

use App\Authorization\CurrentOrganisation;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\BrandDirectoryRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/** Business authorization precedes the scoped repository query. */
final class ListBrandsService
{
    public function __construct(
        private readonly BrandDirectoryRepositoryContract $brands,
        private readonly CurrentOrganisation $context,
    ) {}

    public function execute(User $actor, Organisation $organisation, int $perPage = 20): LengthAwarePaginator
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Page size must be between 1 and 100.');
        }

        if (! $actor->exists
            || $this->context->idFor($actor) !== (int) $organisation->getKey()
            || ! $actor->hasPermission('brand.index')) {
            throw new AuthorizationException('Not permitted to list brands in this organisation.');
        }

        return $this->brands->paginateActiveForMember($actor, (int) $organisation->getKey(), $perPage);
    }
}
