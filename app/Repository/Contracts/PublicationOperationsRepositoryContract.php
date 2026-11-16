<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;

interface PublicationOperationsRepositoryContract
{
    public function staleLeaseIds(int $limit): array;

    public function recoverStale(int $jobId): ?array;

    public function dashboard(Organisation $organisation, Brand $brand, int $limit): array;

    public function timeline(Organisation $organisation, Brand $brand, int $jobId, int $limit): array;
}
