<?php

namespace App\Services\Scheduling;

use App\Domain\Scheduling\RecurringSlotCalculator;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\RecurringSchedule;
use App\Repository\Contracts\RecurringCampaignRepositoryContract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Durable and idempotent schedule expansion. Creates review-only projections,
 * never publication jobs, approvals, network calls, or social dispatches.
 */
final class ExpandRecurringCampaignOccurrences
{
    public function __construct(private readonly RecurringCampaignRepositoryContract $schedules) {}

    public function run(int $limit): array
    {
        if (! config('planner.recurring_expansion_enabled', false)) {
            return ['enabled' => false, 'processed' => 0, 'suggested' => 0];
        }
        $ids = $this->schedules->dueForProjection($limit);
        $processed = 0;
        $suggested = 0;
        $failed = 0;
        foreach ($ids as $id) {
            try {
                $count = DB::transaction(function () use ($id): int {
                    // Consistent order with the first-party hold/state mutations.
                    $candidate = RecurringSchedule::query()->whereKey($id)->first();
                    if (! $candidate) {
                        return 0;
                    }
                    $org = Organisation::query()->whereKey($candidate->organisation_id)->lockForUpdate()->first();
                    if (! $org) {
                        return 0;
                    }
                    $brand = Brand::query()->whereKey($candidate->brand_id)
                        ->where('organisation_id', $org->id)->lockForUpdate()->first();
                    if (! $brand) {
                        return 0;
                    }
                    $schedule = $this->schedules->find($org, $brand, $id, true);
                    $now = CarbonImmutable::now('UTC');
                    if ($schedule->state !== 'ACTIVE'
                        || ($schedule->next_expand_at !== null && CarbonImmutable::parse($schedule->next_expand_at, 'UTC')->greaterThan($now))) {
                        return 0;
                    }
                    $end = $now->addDays(30);
                    $result = RecurringSlotCalculator::expand((int) $schedule->id, (string) $schedule->rrule,
                        (string) $schedule->timezone, (string) $schedule->getRawOriginal('start_local'),
                        $now->toIso8601String(), $end->toIso8601String(),
                        (int) $schedule->max_occurrences,
                        $schedule->end_at ? CarbonImmutable::parse($schedule->getRawOriginal('end_at'), 'UTC')->toIso8601String() : null);
                    $created = 0;
                    foreach ($result['slots'] as $slot) {
                        if ($this->schedules->projectSlot($schedule, $slot)) {
                            $created++;
                        }
                    }
                    // Daily rolling review window. No implicit consent or auto-hold.
                    $this->schedules->advanceProjection($schedule, $now->addDay()->format('Y-m-d H:i:s'));

                    return $created;
                }, 3);
                $processed++;
                $suggested += $count;
            } catch (\Throwable $error) {
                // No remote side effects; isolate a bad template and let other
                // brands proceed. Persist nothing from this failed transaction.
                $failed++;
                report($error);
            }
        }

        return ['enabled' => true, 'processed' => $processed, 'suggested' => $suggested, 'failed' => $failed];
    }
}
