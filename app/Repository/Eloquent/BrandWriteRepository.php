<?php

namespace App\Repository\Eloquent;

use App\Enums\StateEnum;
use App\Enums\StatusEnum;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\BrandWriteRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/** All writes are bound to an active organisation membership and its own role. */
final class BrandWriteRepository implements BrandWriteRepositoryContract
{
    public function __construct(private readonly Brand $model) {}

    public function activeRoleIdFor(Organisation $organisation, User $member): ?int
    {
        $roleId = DB::table('organisation_memberships')
            ->where('organisation_id', $organisation->getKey())
            ->where('user_id', $member->getKey())
            ->where('state', StateEnum::ACTIVE->value)
            ->lockForUpdate()
            ->value('role_id');

        return $roleId !== null ? (int) $roleId : null;
    }

    public function createForMember(
        Organisation $organisation,
        User $creator,
        int $organisationRoleId,
        string $name,
        ?string $websiteUrl,
        string $timezone,
    ): Brand {
        // Defense in depth: no caller can attach an arbitrary role or cross-tenant member.
        $member = DB::table('organisation_memberships')
            ->where('organisation_id', $organisation->getKey())
            ->where('user_id', $creator->getKey())
            ->where('role_id', $organisationRoleId)
            ->where('state', StateEnum::ACTIVE->value)
            ->exists();

        if (! $member) {
            throw new AuthorizationException('An active organisation membership with this role is required.');
        }

        $brand = $this->model->newQuery()->create([
            'organisation_id' => $organisation->getKey(),
            'name' => $name,
            'website_url' => $websiteUrl,
            'timezone' => $timezone,
            'status' => StatusEnum::ACTIVE->value,
        ]);

        $now = now();
        DB::table('brand_memberships')->insert([
            'organisation_id' => $organisation->getKey(),
            'brand_id' => $brand->getKey(),
            'user_id' => $creator->getKey(),
            'role_id' => $organisationRoleId,
            'state' => StateEnum::ACTIVE->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $brand;
    }
}
