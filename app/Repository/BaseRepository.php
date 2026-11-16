<?php

namespace App\Repository;

use App\Repository\Contracts\Persistence;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Generic Eloquent-backed repository.
 *
 * Concrete repositories extend this class, bind their model via the
 * constructor, and add only entity-specific query methods. Every method
 * here is inherited as-is.
 *
 * @template TModel of Model
 *
 * @implements Persistence<TModel>
 */
abstract class BaseRepository implements Persistence
{
    /**
     * @param  TModel  $model
     */
    public function __construct(
        /** @var TModel */
        protected Model $model
    ) {}

    /**
     * Retrieve a record by its primary key.
     *
     * @return TModel|null
     */
    public function find(int $id): ?Model
    {
        return $this->model->newQuery()->find($id);
    }

    /**
     * Retrieve a record by its primary key or throw.
     *
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): Model
    {
        return $this->model->newQuery()->findOrFail($id);
    }

    /**
     * Retrieve the first record matching a single column/value pair.
     *
     * @return TModel|null
     */
    public function findBy(string $column, mixed $value, array $columns = ['*']): ?Model
    {
        return $this->model->newQuery()->where($column, $value)->first($columns);
    }

    /**
     * Create a new record with the given attributes.
     *
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model
    {
        return $this->model->newQuery()->create($attributes);
    }

    /**
     * Update an existing record with the given attributes.
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model
    {
        $model->update($attributes);

        return $model;
    }

    /**
     * Delete an existing record.
     *
     * @param  TModel  $model
     */
    public function delete(Model $model): bool
    {
        return $model->delete();
    }

    /**
     * Find a record matching $exists, or create one if none matches.
     *
     * On create, the row is array_merge($exists, $attributes).
     * On match, $attributes are ignored and the record is returned untouched.
     *
     * @param  array<string, mixed>  $exists
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function findOrCreate(array $exists, array $attributes): Model
    {
        return $this->model->newQuery()->firstOrCreate($exists, $attributes);
    }

    /**
     * Find a record matching $attributes, then update it with $values —
     * or create it if none matches.
     *
     * On create, the row is array_merge($attributes, $values).
     * On match, $values are applied as an update.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     * @return TModel
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        return $this->model->newQuery()->updateOrCreate($attributes, $values);
    }

    /**
     * Retrieve every record, optionally limited to specific columns.
     *
     * @param  array<int, string>  $columns
     * @return Collection<int, TModel>
     */
    public function all(array $columns = ['*']): Collection
    {
        return $this->model->newQuery()->get($columns);
    }

    /**
     * Retrieve a paginated slice of records.
     *
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()->paginate($perPage);
    }

    /**
     * Count all records in the underlying table.
     */
    public function count(): int
    {
        return $this->model->newQuery()->count();
    }

    /**
     * Re-retrieve the model from the database as a new instance.
     *
     * @param  TModel  $model
     * @return TModel|null
     */
    public function fresh(Model $model): ?Model
    {
        return $model->fresh();
    }

    /**
     * Re-hydrate the given model instance in place with fresh data.
     *
     * @param  TModel  $model
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function refresh(Model $model): Model
    {
        return $model->refresh();
    }

    /**
     * Determine whether any record matches the given conditions.
     *
     * @param  array<string, mixed>  $conditions
     */
    public function exists(array $conditions): bool
    {
        return $this->model->newQuery()->where($conditions)->exists();
    }

    /**
     * Find a record matching $attributes, or return a new (unsaved) instance.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     * @return TModel
     */
    public function firstOrNew(array $attributes, array $values = []): Model
    {
        return $this->model->newQuery()->firstOrNew($attributes, $values);
    }

    /**
     * Process all matching records in slices of $count to bound memory use.
     *
     * By default this delegates to Eloquent's chunk(), which paginates by
     * OFFSET. It is safe when the result set is not modified during
     * iteration. If records may be inserted or deleted between chunks,
     * pass $byId = true to delegate to chunkById(), which paginates on the
     * model's primary key and avoids skipped/duplicated rows — provided the
     * primary key is a single, monotonically increasing column
     * (e.g. auto-increment int). chunkById() does NOT guarantee correct
     * ordering for UUIDv4/ULID/string primary keys.
     *
     * The callback receives a Collection of TModel per chunk. Returning
     * false from the callback stops iteration early.
     *
     * @param  bool  $byId  True to paginate by primary key (chunkById),
     *                      false to paginate by offset (chunk).
     * @return bool False if iteration was stopped early, true otherwise.
     */
    public function chunk(int $count, \Closure $callback, bool $byId = false): bool
    {
        $query = $this->model->newQuery();

        return $byId
            ? $query->chunkById($count, $callback)
            : $query->chunk($count, $callback);
    }

    /**
     * Return a fresh query builder for the underlying model.
     *
     * This is an explicit escape hatch for queries the repository contract
     * does not cover (aggregates, raw expressions, scopes, eager loading,
     * etc.). It is intentionally NOT part of PersistenceContract: calling
     * it means the caller has chosen to depend on Eloquent directly, which
     * defeats the repository's swapability and test-double guarantees for
     * that call site. Use it sparingly and keep the logic it builds local.
     *
     * Every call returns a NEW builder, so no WHERE/SELECT state is carried
     * between calls. Do not cache or reuse the returned builder across
     * logical operations — build, execute, discard.
     *
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return $this->model->newQuery();
    }
}
