<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;

interface AuditEventRepositoryContract
{
    /** Insert in the caller's transaction so rolled-back mutations leave no success audit. */
    public function record(
        Organisation $organisation,
        User $actor,
        string $action,
        string $resourceKind,
        string $resourceId,
        ?Brand $brand = null,
        array $metadata = [],
    ): void;
}
