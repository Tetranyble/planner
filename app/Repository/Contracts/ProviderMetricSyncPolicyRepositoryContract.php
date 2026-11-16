<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderMetricSyncPolicy;
use App\Models\User;

interface ProviderMetricSyncPolicyRepositoryContract
{
    public function get(Organisation $org, Brand $brand, bool $lock = false): ?ProviderMetricSyncPolicy;

    public function upsert(Organisation $org, Brand $brand, User $user, int $hour, int $limit, bool $enabled, ?int $version): ProviderMetricSyncPolicy;

    public function dueIds(int $utcHour, int $limit): array;

    public function lockById(int $id): ?ProviderMetricSyncPolicy;
}
