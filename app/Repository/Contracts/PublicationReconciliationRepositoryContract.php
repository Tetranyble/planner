<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;

interface PublicationReconciliationRepositoryContract
{
    public function history(Organisation $org, Brand $brand, int $jobId): array;

    public function submit(Organisation $org, Brand $brand, int $jobId, int $actorId, array $payload): array;

    public function decide(Organisation $org, Brand $brand, int $jobId, int $actorId, array $payload): array;
}
