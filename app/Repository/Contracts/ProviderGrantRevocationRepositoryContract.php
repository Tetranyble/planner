<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderGrantRevocationRequest;
use App\Social\Data\RevocationLease;

interface ProviderGrantRevocationRepositoryContract
{
    public function prepare(Organisation $organisation, Brand $brand, string $accountUuid,
        int $actorId, string $reason): ProviderGrantRevocationRequest;

    public function confirm(Organisation $organisation, string $requestUuid, int $actorId): ProviderGrantRevocationRequest;

    /** @return list<array<string,mixed>> */
    public function directory(Organisation $organisation): array;

    /** @return list<int> */
    public function dueIds(int $limit): array;

    public function acquire(int $requestId): ?RevocationLease;

    public function complete(RevocationLease $lease): bool;

    public function uncertain(RevocationLease $lease): void;
}
