<?php

namespace App\Repository\Eloquent;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerOperationalAlert;
use App\Models\PlannerOperationalAlertEvent;
use App\Repository\Contracts\PlannerOperationalAlertRepositoryContract;
use Illuminate\Support\Facades\DB;

/** Brand-locked snapshot transitions, with append-only state-change evidence. */
final class PlannerOperationalAlertRepository implements PlannerOperationalAlertRepositoryContract
{
    public function reconcile(Organisation $org, Brand $brand, array $counts): int
    {
        return DB::transaction(function () use ($org, $brand, $counts): int {
            // Serializes two monitors running against the same brand.
            Brand::query()->whereKey($brand->id)->where('organisation_id', $org->id)
                ->lockForUpdate()->firstOrFail();
            $changed = 0;
            foreach ($counts as $signal => $rawCount) {
                if (! preg_match('/^[a-z_]{3,60}$/D', $signal)
                    || ! is_int($rawCount) || $rawCount < 0) {
                    throw new \InvalidArgumentException('Invalid operational counter.');
                }
                $current = PlannerOperationalAlert::query()->where('organisation_id', $org->id)
                    ->where('brand_id', $brand->id)->where('signal', $signal)->lockForUpdate()->first();
                if ($current === null && $rawCount === 0) {
                    continue;
                }
                $state = $rawCount > 0 ? 'OPEN' : 'RESOLVED';
                if ($current === null) {
                    $current = PlannerOperationalAlert::query()->create([
                        'organisation_id' => $org->id, 'brand_id' => $brand->id, 'signal' => $signal,
                        'state' => 'OPEN', 'observed_count' => $rawCount,
                        'first_seen_at' => now(), 'last_seen_at' => now(),
                    ]);
                    $event = 'OPENED';
                } else {
                    $event = $current->state !== $state
                        ? ($state === 'OPEN' ? 'REOPENED' : 'RESOLVED')
                        : ($current->observed_count !== $rawCount ? 'CHANGED' : null);
                    $current->forceFill([
                        'state' => $state, 'observed_count' => $rawCount, 'last_seen_at' => now(),
                        'resolved_at' => $state === 'RESOLVED' ? now() : null,
                    ])->save();
                }
                if ($event !== null) {
                    PlannerOperationalAlertEvent::query()->create([
                        'organisation_id' => $org->id, 'brand_id' => $brand->id,
                        'planner_operational_alert_id' => $current->getKey(),
                        'event_type' => $event, 'observed_count' => $rawCount, 'observed_at' => now(),
                    ]);
                    $changed++;
                }
            }

            return $changed;
        }, 3);
    }

    public function list(Organisation $org, Brand $brand): array
    {
        return PlannerOperationalAlert::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->orderBy('signal')->limit(30)
            ->get(['signal', 'state', 'observed_count', 'last_seen_at', 'resolved_at'])
            ->map(static fn ($a) => [
                'signal' => $a->signal, 'state' => $a->state, 'count' => (int) $a->observed_count,
                'last_seen_at' => $a->last_seen_at?->toIso8601String(),
                'resolved_at' => $a->resolved_at?->toIso8601String(),
            ])->all();
    }
}
