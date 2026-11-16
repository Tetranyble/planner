<?php

namespace App\Repository\Contracts;

use App\Social\Data\MetaInspectionLease;
use App\Social\Data\MetaTokenInspection;

interface MetaCapabilityInspectionRepositoryContract
{
    /** @return list<int> */
    public function dueIds(int $limit, int $minutes): array;

    public function acquire(int $connectionId, int $minutes): ?MetaInspectionLease;

    public function complete(MetaInspectionLease $lease, MetaTokenInspection $inspection): bool;

    public function fail(MetaInspectionLease $lease): void;
}
