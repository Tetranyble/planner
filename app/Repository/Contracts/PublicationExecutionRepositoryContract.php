<?php

namespace App\Repository\Contracts;

use App\Domain\Publishing\Delivery\DispatchOutcome;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PublicationJob;

interface PublicationExecutionRepositoryContract
{
    public function arm(Organisation $organisation, Brand $brand, PublicationJob $lockedJob, int $actorId, int $expectedVersion): PublicationJob;

    /** @return list<int> */
    public function dueIds(int $limit): array;

    /** @return array{id:int,owner:string,organisation_id:int,brand_id:int,armed_by_user_id:int}|null */
    public function claim(int $jobId, string $leaseOwner): ?array;

    /** @return array{job:PublicationJob,organisation:Organisation,brand:Brand}|null */
    public function scopedLease(int $jobId, string $owner): ?array;

    public function holdBlocked(int $jobId, string $owner, string $code): bool;

    public function startAttempt(int $jobId, string $owner): ?string;

    public function attemptIsFenced(int $jobId, string $owner, string $operationKey): bool;

    public function finishAttempt(int $jobId, string $owner, string $operationKey, DispatchOutcome $outcome): bool;
}
