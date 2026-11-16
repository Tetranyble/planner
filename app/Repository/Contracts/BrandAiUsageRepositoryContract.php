<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\BrandAiUsageReservation;
use App\Models\Organisation;
use App\Models\User;

interface BrandAiUsageRepositoryContract
{
    /** Must be called in a DB transaction after locking the brand row. */
    public function reserve(Organisation $org, Brand $brand, User $actor, string $purpose, int $limit): BrandAiUsageReservation;

    public function settle(BrandAiUsageReservation $reservation, bool $success, ?string $model, ?string $hash, ?array $usage = null): void;

    public function markUncertain(BrandAiUsageReservation $reservation): void;

    public function usage(Organisation $org, Brand $brand, int $limit): array;
}
