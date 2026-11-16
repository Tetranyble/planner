<?php

namespace App\Repository\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Generic persistence contract shared by all repositories.
 *
 * @template TModel of Model
 */
interface Persistence
{
    /**
     * Retrieve a record by its primary key.
     *
     * @return TModel|null The matching record, or null if none exists.
     */
    public function find(int $id): ?Model;

    /**
     * Retrieve a record by its primary key or throw.
     *
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): Model;

    /**
     * Retrieve the first record matching a single column/value pair.
     *
     * @return TModel|null The matching record, or null if none exists.
     */
    public function findBy(string $column, mixed $value, array $columns = ['*']): ?Model;

    /**
     * Create a new record with the given attributes.
     *
     * @param  array<string, mixed>  $attributes
     * @return TModel The freshly created (and persisted) record.
     */
    public function create(array $attributes): Model;

    /**
     * Update an existing record with the given attributes.
     *
     * Only the supplied attributes are written; others are untouched.
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel The same instance, refreshed with new values.
     */
    public function update(Model $model, array $attributes): Model;

    /**
     * Delete an existing record.
     *
     * @param  TModel  $model
     * @return bool True on success, false otherwise.
     */
    public function delete(Model $model): bool;

    /**
     * Find a record matching $exists, or create one if none matches.
     *
     * When creating, the inserted row is array_merge($exists, $attributes),
     * so $exists values are always present on the new record.
     * When a match is found, $attributes are ignored and the record is
     * returned untouched (no update).
     *
     * @param  array<string, mixed>  $exists  Search criteria; also included on create.
     * @param  array<string, mixed>  $attributes  Extra values applied only on create.
     * @return TModel
     */
    public function findOrCreate(array $exists, array $attributes): Model;

    /**
     * Find a record matching $attributes, then update it with $values —
     * or create it if none matches.
     *
     * When creating, the row is array_merge($attributes, $values).
     * When a match is found, $values are applied as an update.
     *
     * @param  array<string, mixed>  $attributes  Search criteria; also included on create.
     * @param  array<string, mixed>  $values  Values applied on create and on update.
     * @return TModel
     */
    public function updateOrCreate(array $attributes, array $values = []): Model;

    /**
     * Retrieve every record, optionally limited to specific columns.
     *
     * @param  array<int, string>  $columns
     * @return Collection<int, TModel>
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Retrieve a paginated slice of records.
     *
     * @param  int  $perPage  Number of records per page (must be > 0).
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * Count all records in the underlying table.
     */
    public function count(): int;

    /**
     * Re-retrieve the model from the database as a new instance.
     *
     * Does NOT mutate the passed model. Returns null if the record
     * no longer exists. Relationships are not loaded on the result.
     *
     * @param  TModel  $model
     * @return TModel|null
     */
    public function fresh(Model $model): ?Model;

    /**
     * Re-hydrate the given model instance in place with fresh data.
     *
     * Mutates the passed instance. Loaded relationships are reloaded.
     * Throws if the record no longer exists (Eloquent's refresh() behavior).
     *
     * @param  TModel  $model
     * @return TModel The same instance, re-hydrated.
     *
     * @throws ModelNotFoundException
     */
    public function refresh(Model $model): Model;

    /**
     * Determine whether any record matches the given conditions.
     *
     * Uses an EXISTS-style query; does not load models.
     *
     * @param  array<string, mixed>  $conditions
     */
    public function exists(array $conditions): bool;

    /**
     * Find a record matching $attributes, or return a new (unsaved)
     * instance when none matches.
     *
     * On create-candidate, the instance is array_merge($attributes, $values).
     * The caller is responsible for persisting it if desired.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     * @return TModel
     */
    public function firstOrNew(array $attributes, array $values = []): Model;

    /**
     * Process all matching records in slices of $count to bound memory use.
     *
     * The callback receives a Collection of TModel per chunk. Returning
     * false from the callback stops iteration early.
     *
     * @return bool False if iteration was stopped early, true otherwise.
     */
    public function chunk(int $count, \Closure $callback, bool $byId = false): bool;

    /**
     * Return a fresh query builder for the underlying model.
     *
     * Escape hatch for queries the repository does not cover. Use sparingly;
     * anything built here is no longer abstracted by the repository and
     * cannot be swapped or mocked.
     *
     * @return Builder<TModel>
     */
    public function query(): Builder;
}
