<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;

interface BrandOperationsMetricsRepositoryContract
{
    /** Counts only this organisation and brand, inclusive UTC dates; never fetches provider secrets. */
    public function aggregate(Organisation $organisation, Brand $brand, string $from, string $untilExclusive): array;
}
