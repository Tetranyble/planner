<?php

namespace App\Services\Directory;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;

/** Both JSON API and Inertia share one explicit, safe serialization boundary. */
final class DirectoryPage
{
    /** @param class-string<JsonResource> $resource */
    public static function from(LengthAwarePaginator $paginator, string $resource): array
    {
        return [
            'data' => collect($paginator->items())->map(
                fn ($model): array => $resource::make($model)->resolve()
            )->values()->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
