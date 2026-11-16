<?php

namespace App\Repository\Contracts;

use App\Models\AiContentSuggestion;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AiSuggestionRepositoryContract
{
    public function list(Organisation $org, Brand $brand): LengthAwarePaginator;

    public function find(Organisation $org, Brand $brand, string $uuid, bool $lock = false): AiContentSuggestion;

    public function create(Organisation $org, Brand $brand, User $actor, array $values): AiContentSuggestion;

    public function promote(AiContentSuggestion $suggestion, User $actor, int $itemId): void;
}
