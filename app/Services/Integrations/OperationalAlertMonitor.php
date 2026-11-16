<?php

namespace App\Services\Integrations;

use App\Models\Brand;
use App\Models\Organisation;
use App\Repository\Contracts\PlannerOperationalAlertRepositoryContract;
use App\Repository\Contracts\PlannerOperationalSafetyRepositoryContract;

/** No provider calls, email, publication changes, or cross-tenant exposures. */
final class OperationalAlertMonitor
{
    public function __construct(
        private readonly PlannerOperationalSafetyRepositoryContract $safety,
        private readonly PlannerOperationalAlertRepositoryContract $alerts,
    ) {}

    public function check(Organisation $org, Brand $brand): int
    {
        return $this->alerts->reconcile($org, $brand, $this->safety->summary($org, $brand));
    }
}
