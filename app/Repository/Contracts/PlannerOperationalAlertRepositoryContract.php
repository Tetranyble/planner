<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;

interface PlannerOperationalAlertRepositoryContract
{
    /** @param array<string, int> $counts */
    public function reconcile(Organisation $org, Brand $brand, array $counts): int;

    public function list(Organisation $org, Brand $brand): array;
}
