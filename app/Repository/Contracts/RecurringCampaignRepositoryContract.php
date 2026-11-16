<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\RecurringSchedule;
use Illuminate\Support\Collection;

interface RecurringCampaignRepositoryContract
{
    public function listing(Organisation $org, Brand $brand): Collection;

    public function find(Organisation $org, Brand $brand, int $id, bool $lock = false): RecurringSchedule;

    public function ownsTarget(Organisation $org, Brand $brand, string $targetUuid): ?int;

    public function create(Organisation $org, Brand $brand, int $targetId, int $actorId, array $data): RecurringSchedule;

    /** @return list<int> */
    public function dueForProjection(int $limit): array;

    public function advanceProjection(RecurringSchedule $schedule, string $nextUtc): void;

    public function projectSlot(RecurringSchedule $schedule, array $slot): bool;

    public function markHeldProjection(RecurringSchedule $schedule, array $slot): void;

    public function projected(Organisation $org, Brand $brand, RecurringSchedule $schedule): Collection;

    public function transition(RecurringSchedule $schedule, string $nextState): void;
}
