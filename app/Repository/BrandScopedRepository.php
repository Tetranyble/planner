<?php

namespace App\Repository;

use App\Repository\Contracts\BrandScopedPersistence;
use App\Repository\Support\BrandScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The ONLY reusable base for two-dimensional brand-owned persistence.
 * Do not extend BaseRepository: it exposes unscoped find/all/query methods.
 *
 * @template TModel of Model
 *
 * @implements BrandScopedPersistence<TModel>
 */
abstract class BrandScopedRepository implements BrandScopedPersistence
{
    /** @param TModel $model */
    public function __construct(protected Model $model) {}

    /** @return Builder<TModel> */
    final protected function scopedQuery(BrandScope $scope): Builder
    {
        return $this->model->newQuery()
            ->where('organisation_id', $scope->organisationId)
            ->where('brand_id', $scope->brandId);
    }

    public function findForBrand(int $organisationId, int $brandId, int $id): ?Model
    {
        $scope = new BrandScope($organisationId, $brandId);

        return $this->scopedQuery($scope)->find($id);
    }

    public function findOrFailForBrand(int $organisationId, int $brandId, int $id): Model
    {
        $scope = new BrandScope($organisationId, $brandId);

        return $this->scopedQuery($scope)->findOrFail($id);
    }

    public function paginateForBrand(int $organisationId, int $brandId, int $perPage = 20): LengthAwarePaginator
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new \InvalidArgumentException('Page size must be between 1 and 100.');
        }

        $scope = new BrandScope($organisationId, $brandId);

        return $this->scopedQuery($scope)->orderBy('id')->paginate($perPage);
    }

    public function createForBrand(int $organisationId, int $brandId, array $attributes): Model
    {
        $scope = new BrandScope($organisationId, $brandId);
        $scope->assertWritableAttributes($attributes);
        $this->assertModelFillable($attributes);

        // Fill only the model's explicit allowlist before setting trusted scope.
        $model = $this->model->newInstance();
        $model->fill($attributes);
        $model->setAttribute('organisation_id', $scope->organisationId);
        $model->setAttribute('brand_id', $scope->brandId);
        $model->saveOrFail();

        return $model;
    }

    public function updateForBrand(int $organisationId, int $brandId, int $id, array $attributes): Model
    {
        $scope = new BrandScope($organisationId, $brandId);
        $scope->assertWritableAttributes($attributes);
        $this->assertModelFillable($attributes);

        $model = $this->scopedQuery($scope)->findOrFail($id);
        $model->fill($attributes);
        $model->saveOrFail();

        return $model;
    }

    /** @param array<string, mixed> $attributes */
    final protected function assertModelFillable(array $attributes): void
    {
        foreach (array_keys($attributes) as $attribute) {
            if (! is_string($attribute) || ! $this->model->isFillable($attribute)) {
                throw new \InvalidArgumentException('Unsupported writable attribute for '.get_class($this->model));
            }
        }
    }

    public function deleteForBrand(int $organisationId, int $brandId, int $id): bool
    {
        $scope = new BrandScope($organisationId, $brandId);
        $model = $this->scopedQuery($scope)->findOrFail($id);

        return (bool) $model->delete();
    }
}
