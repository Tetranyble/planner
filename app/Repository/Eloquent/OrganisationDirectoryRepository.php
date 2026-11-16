<?php

namespace App\Repository\Eloquent;

use App\Enums\StateEnum;
use App\Enums\StatusEnum;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\OrganisationDirectoryRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Restricts discovery at the query boundary, including org lifecycle. */
final class OrganisationDirectoryRepository implements OrganisationDirectoryRepositoryContract
{
    public function __construct(private readonly Organisation $model) {}

    public function paginateActiveForMember(User $actor, int $perPage): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->join('organisation_memberships as membership', 'membership.organisation_id', '=', 'organisations.id')
            ->where('membership.user_id', $actor->getKey())
            ->where('membership.state', StateEnum::ACTIVE->value)
            ->where('organisations.status', StatusEnum::ACTIVE->value)
            ->select(['organisations.id', 'organisations.uuid', 'organisations.name', 'organisations.timezone', 'organisations.status'])
            ->orderBy('organisations.id')
            ->paginate($perPage);
    }
}
