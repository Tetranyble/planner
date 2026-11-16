<?php

namespace App\Repository\Eloquent;

use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\StorageMediaCandidateRepositoryContract;
use Illuminate\Support\Collection;
use Tetranyble\Storage\Modules\Media\Infrastructure\Persistence\Eloquent\Models\Media;

final class StorageMediaCandidateRepository implements StorageMediaCandidateRepositoryContract
{
    public function candidates(Organisation $org, User $actor, int $beforeId, int $limit): Collection
    {
        return Media::query()->where('workspace_id', $org->id)->where('uploaded_by', $actor->id)
            ->whereNull('deleted_at')->whereNull('archived_at')->whereNull('quarantined_at')
            ->where('is_temporary', false)->when($beforeId > 0, fn ($query) => $query->where('id', '<', $beforeId))
            ->orderByDesc('id')->limit(max(1, min(40, $limit)))->get();
    }
}
