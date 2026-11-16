<?php

namespace App\Concerns;

use App\Enums\StatusEnum;
use Illuminate\Database\Eloquent\Builder;

trait Searchable
{
    public function scopeSearch(Builder $builder, ?string $terms = null, array $additionalColumns = []): void
    {
        $columns = array_merge($this->getSearchableColumns(), $additionalColumns);

        $builder->when($terms ?? false, function ($builder) use ($terms, $columns) {
            $words = array_filter(explode(' ', trim($terms)));
            $builder->where(function ($builder) use ($words, $columns) {
                foreach ($words as $word) {
                    $wrappedWord = '%'.$word.'%';
                    $builder->where(function ($subQuery) use ($wrappedWord, $columns) {
                        foreach ($columns as $column) {
                            $subQuery->orWhere($column, 'like', $wrappedWord);
                        }
                    });
                }
            });
        });
    }

    /**
     * Scope a query by status when a filter is provided.
     */
    public function scopeStatus(Builder $query, ?StatusEnum $status = null): Builder
    {
        return $query->when(
            $status !== null,
            fn (Builder $query): Builder => $query->where('status', $status->value)
        );
    }

    protected function getSearchableColumns(): array
    {
        return property_exists($this, 'searchableColumns') ? $this->searchableColumns : [];
    }

    public function scopeSortOrder(Builder $builder, ?string $sortField = null, ?string $sortDirection = null): Builder
    {
        $sortField = $sortField ?: $this->getDefaultSortField();
        $sortDirection = strtolower($sortDirection ?: $this->getDefaultSortDirection());

        if (! in_array($sortField, $this->getSortableColumns(), true)) {
            $sortField = $this->getDefaultSortField();
        }

        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = $this->getDefaultSortDirection();
        }

        return $builder->orderBy($sortField, $sortDirection);
    }

    protected function getSortableColumns(): array
    {
        return property_exists($this, 'sortableColumns')
            ? $this->sortableColumns
            : ['id', 'created_at', 'updated_at'];
    }

    protected function getDefaultSortField(): string
    {
        return property_exists($this, 'defaultSortField')
            ? $this->defaultSortField
            : 'created_at';
    }

    protected function getDefaultSortDirection(): string
    {
        return property_exists($this, 'defaultSortDirection')
            ? strtolower($this->defaultSortDirection)
            : 'desc';
    }
}
