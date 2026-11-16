<?php

namespace App\Repository\Eloquent;

use App\Models\AiContentSuggestion;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AiSuggestionRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class AiSuggestionRepository implements AiSuggestionRepositoryContract
{
    private function scoped(Organisation $org, Brand $brand)
    {
        return AiContentSuggestion::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id);
    }

    public function list(Organisation $org, Brand $brand): LengthAwarePaginator
    {
        return $this->scoped($org, $brand)->latest('id')->paginate(20);
    }

    public function find(Organisation $org, Brand $brand, string $uuid, bool $lock = false): AiContentSuggestion
    {
        $q = $this->scoped($org, $brand)->where('uuid', $uuid);

        return ($lock ? $q->lockForUpdate() : $q)->firstOrFail();
    }

    public function create(Organisation $org, Brand $brand, User $actor, array $values): AiContentSuggestion
    {
        return AiContentSuggestion::query()->forceCreate([...$values, 'uuid' => (string) Str::uuid(),
            'organisation_id' => $org->id, 'brand_id' => $brand->id, 'requested_by_user_id' => $actor->id, 'status' => 'SUGGESTED']);
    }

    public function promote(AiContentSuggestion $suggestion, User $actor, int $itemId): void
    {
        $suggestion->status = 'PROMOTED';
        $suggestion->promoted_content_item_id = $itemId;
        $suggestion->promoted_by_user_id = $actor->id;
        $suggestion->promoted_at = now();
        $suggestion->save();
    }
}
