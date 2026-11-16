<?php

namespace App\Repository\Contracts;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Collection;

interface StorageMediaCandidateRepositoryContract
{
    /** Workspace-owned, actor-uploaded media only. Caller rechecks canonical safety. */
    public function candidates(Organisation $org, User $actor, int $beforeId, int $limit): Collection;
}
