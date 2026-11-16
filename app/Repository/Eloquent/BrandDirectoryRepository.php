<?php

namespace App\Repository\Eloquent;

use App\Enums\StateEnum;
use App\Enums\StatusEnum;
use App\Models\Brand;
use App\Models\OrganisationMembership;
use App\Models\User;
use App\Repository\Contracts\BrandDirectoryRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Tetranyble\Kinship\Models\Role;

/** No generic BaseRepository inheritance; all queries are tenant- and member-scoped. */
final class BrandDirectoryRepository implements BrandDirectoryRepositoryContract
{
    public function __construct(private readonly Brand $model) {}

    public function paginateActiveForMember(User $actor, int $organisationId, int $perPage): LengthAwarePaginator
    {
        // Organisation owners retain brand-directory visibility for membership recovery.
        // This does NOT grant content or publication access; use BrandPermissionGuard for that.
        $ownerRole = Role::query()->forWorkspace($organisationId)->where('name', 'owner')->first();
        $isOwner = $ownerRole !== null && OrganisationMembership::query()
            ->where('organisation_id', $organisationId)->where('user_id', $actor->getKey())
            ->where('role_id', $ownerRole->getKey())
            ->where('state', StateEnum::ACTIVE->value)->exists();
        if ($isOwner) {
            return $this->model->newQuery()
                ->where('organisation_id', $organisationId)
                ->where('status', StatusEnum::ACTIVE->value)
                ->select(['id', 'uuid', 'organisation_id', 'name', 'website_url', 'timezone', 'status'])
                ->orderBy('id')->paginate($perPage);
        }

        return $this->model->newQuery()
            ->join('brand_memberships as brand_member', function ($join): void {
                $join->on('brand_member.brand_id', '=', 'brands.id')
                    ->on('brand_member.organisation_id', '=', 'brands.organisation_id');
            })
            ->join('organisation_memberships as org_member', function ($join): void {
                $join->on('org_member.organisation_id', '=', 'brand_member.organisation_id')
                    ->on('org_member.user_id', '=', 'brand_member.user_id');
            })
            ->join('organisations as org', 'org.id', '=', 'brands.organisation_id')
            ->where('brands.organisation_id', $organisationId)
            ->where('brand_member.user_id', $actor->getKey())
            ->where('brand_member.state', StateEnum::ACTIVE->value)
            ->where('org_member.state', StateEnum::ACTIVE->value)
            ->where('org.status', StatusEnum::ACTIVE->value)
            ->where('brands.status', StatusEnum::ACTIVE->value)
            ->select(['brands.id', 'brands.uuid', 'brands.organisation_id', 'brands.name', 'brands.website_url', 'brands.timezone', 'brands.status'])
            ->orderBy('brands.id')
            ->paginate($perPage);
    }
}
