<?php

namespace App\Repository\Contracts;

use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\ContentTarget;
use App\Models\Organisation;
use App\Models\PublicationJob;
use Illuminate\Support\Collection;

interface PublicationIntentRepositoryContract
{
    public function findByOccurrence(Organisation $org, Brand $brand, ContentTarget $target, string $key): ?PublicationJob;

    public function createHeld(Organisation $org, Brand $brand, ContentTarget $target, ApprovalRequest $approval, string $key, string $runAt): PublicationJob;

    public function listing(Organisation $org, Brand $brand, int $limit): Collection;

    public function findForInspection(Organisation $org, Brand $brand, int $id): PublicationJob;

    public function findLocked(Organisation $org, Brand $brand, int $id): PublicationJob;

    public function hasUncancelledMediaRun(Organisation $org, Brand $brand, PublicationJob $job): bool;

    public function cancel(PublicationJob $job): void;

    /** @return array{item_uuid:string,target_uuid:string} */
    public function context(Organisation $org, Brand $brand, PublicationJob $job): array;

    /** @return array{item_uuid:string,target_uuid:string} */
    public function contextForTarget(Organisation $org, Brand $brand, int $targetId): array;

    public function reschedule(PublicationJob $job, string $utcTime): void;

    public function dueHeld(int $limit): Collection;

    public function between(Organisation $org, Brand $brand, string $fromInclusive, string $toExclusive, int $limit): Collection;
}
