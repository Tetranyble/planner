<?php

namespace App\Repository\Contracts;

use App\Models\MetaDataDeletionTarget;
use App\Models\Organisation;
use App\Models\User;

interface MetaDeletionReviewRepositoryContract
{
    /** @return array<int,array<string,mixed>> */
    public function directory(Organisation $organisation): array;

    public function isActiveOwner(Organisation $organisation, User $actor): bool;

    /** Locks the request before the target to match the processing worker lock order. */
    public function lockReviewTarget(Organisation $organisation, int $targetId): MetaDataDeletionTarget;

    /** @param array<string,mixed> $decision */
    public function resolve(MetaDataDeletionTarget $target, User $reviewer, array $decision): void;
}
