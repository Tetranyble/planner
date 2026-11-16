<?php

namespace App\Repository\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Database boundary for records that carry BOTH organisation_id and brand_id.
 * Intentionally DOES NOT extend the global/unscoped Persistence contract.
 * These methods enforce scope, not permission: authorization belongs to services.
 *
 * @template TModel of Model
 */
interface BrandScopedPersistence
{
    /** @return TModel|null */
    public function findForBrand(int $organisationId, int $brandId, int $id): ?Model;

    /** @return TModel */
    public function findOrFailForBrand(int $organisationId, int $brandId, int $id): Model;

    /** @return LengthAwarePaginator<int, TModel> */
    public function paginateForBrand(int $organisationId, int $brandId, int $perPage = 20): LengthAwarePaginator;

    /** @param array<string, mixed> $attributes @return TModel */
    public function createForBrand(int $organisationId, int $brandId, array $attributes): Model;

    /** @param array<string, mixed> $attributes @return TModel */
    public function updateForBrand(int $organisationId, int $brandId, int $id, array $attributes): Model;

    public function deleteForBrand(int $organisationId, int $brandId, int $id): bool;
}
