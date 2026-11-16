<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;

interface PlannerOperationalSafetyRepositoryContract
{
    /** Aggregated counts only; no remote identifiers, PII, credentials or request payloads. */
    public function summary(Organisation $org, Brand $brand): array;
}
