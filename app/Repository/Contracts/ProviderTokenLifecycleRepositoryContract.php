<?php

namespace App\Repository\Contracts;

use App\Social\Data\TokenRefreshLease;
use App\Social\Data\TokenRefreshResult;

interface ProviderTokenLifecycleRepositoryContract
{
    /** @return list<int> */
    public function dueIds(int $limit, int $leadMinutes): array;

    /** Acquires an exclusive short lease, or handles stale/missing credentials without remote I/O. */
    public function acquire(int $connectionId, int $leadMinutes): ?TokenRefreshLease;

    public function complete(TokenRefreshLease $lease, TokenRefreshResult $result): bool;

    public function fail(TokenRefreshLease $lease, string $safeCode, bool $retrySafe): void;
}
