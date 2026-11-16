<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerAgentAutonomousRun;
use App\Models\PlannerAgentDelegation;
use App\Models\User;

interface PlannerAgentAutonomyRepositoryContract
{
    public function queue(Organisation $org, Brand $brand, User $actor, PlannerAgentDelegation $grant, array $payload): PlannerAgentAutonomousRun;

    public function find(Organisation $org, Brand $brand, PlannerAgentDelegation $grant, string $uuid, bool $lock = false): PlannerAgentAutonomousRun;

    public function list(Organisation $org, Brand $brand, int $limit = 40, ?int $delegationId = null): array;

    public function claim(): ?array;

    public function completeResearch(int $id, string $fence, array $evidence): bool;

    public function completeResource(int $id, string $fence, array $resource): bool;

    public function quarantine(int $id, string $fence, string $code): bool;

    public function quarantineExpired(int $limit): int;

    public function revise(Organisation $org, Brand $brand, PlannerAgentDelegation $grant, User $actor, string $uuid, string $expectedHash, string $text, string $reason): array;

    public function promoted(PlannerAgentAutonomousRun $run, string $workOrderUuid, string $contentUuid): void;

    public function cancel(Organisation $org,Brand $brand,PlannerAgentAutonomousRun $run): bool;
}
