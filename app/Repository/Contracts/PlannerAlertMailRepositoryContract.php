<?php

namespace App\Repository\Contracts;

interface PlannerAlertMailRepositoryContract
{
    public function queue(int $limit): int;

    public function claim(): ?array;

    public function quarantineExpired(int $limit): int;

    public function finish(int $id, string $owner, bool $sent): bool;

    public function summary(int $organisationId, int $brandId): array;
}
