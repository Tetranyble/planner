<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\SocialMetricSnapshot;

interface SocialMetricRepositoryContract
{
    public function findSubmission(Organisation $org, Brand $brand, string $key): ?SocialMetricSnapshot;

    public function store(Organisation $org, Brand $brand, int $userId, array $values): SocialMetricSnapshot;

    public function report(Organisation $org, Brand $brand, string $from, string $to): array;
}
