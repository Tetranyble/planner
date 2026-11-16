<?php

namespace App\Services\Ai;

use App\Models\Brand;
use App\Models\BrandAiUsageReservation;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\BrandAiUsageRepositoryContract;
use Illuminate\Support\Facades\DB;

/** Fail-closed, per-brand UTC daily budget for both AI generation entry points. */
final class BrandAiUsageBudget
{
    public function __construct(private readonly BrandAiUsageRepositoryContract $repo) {}

    private function limit(): int
    {
        return max(1, min(1000, (int) config('services.planner_ai.daily_brand_limit', 20)));
    }

    public function reserve(Organisation $org, Brand $brand, User $actor, string $purpose): BrandAiUsageReservation
    {
        return DB::transaction(function () use ($org, $brand, $actor, $purpose) {
            // Row locks rather than count-only checks guard concurrent generation requests.
            Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();

            return $this->repo->reserve($org, $brand, $actor, $purpose, $this->limit());
        }, 3);
    }

    public function settle(BrandAiUsageReservation $reservation, bool $success, ?string $model = null, ?string $hash = null, ?array $usage = null): void
    {
        DB::transaction(fn () => $this->repo->settle($reservation, $success, $model, $hash, $usage), 3);
    }

    public function markUncertain(BrandAiUsageReservation $reservation): void
    {
        DB::transaction(fn () => $this->repo->markUncertain($reservation), 3);
    }

    public function usage(Organisation $org, Brand $brand): array
    {
        return $this->repo->usage($org,$brand,$this->limit());
    }
}
