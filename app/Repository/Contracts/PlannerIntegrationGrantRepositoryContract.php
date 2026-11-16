<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;

interface PlannerIntegrationGrantRepositoryContract
{
    public function issue(Organisation $org, Brand $brand, User $owner, string $name, int $days): array;

    public function list(Organisation $org, Brand $brand, User $owner): array;

    public function revoke(Organisation $org, Brand $brand, User $owner, int $grantId): bool;

    public function pruneExpired(int $limit): int;

    /** @return array{organisation: Organisation, brand: Brand}|null */
    public function scopeForToken(int $sanctumTokenId, User $owner): ?array;

    public function allows(int $sanctumTokenId, Organisation $org, Brand $brand, User $owner): bool;
}
