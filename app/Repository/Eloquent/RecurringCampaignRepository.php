<?php

namespace App\Repository\Eloquent;

use App\Models\Brand;
use App\Models\ContentTarget;
use App\Models\Organisation;
use App\Models\RecurringCampaignOccurrence;
use App\Models\RecurringSchedule;
use App\Repository\Contracts\RecurringCampaignRepositoryContract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Writes are only initiated by the authenticated, first-party campaign service. */
final class RecurringCampaignRepository implements RecurringCampaignRepositoryContract
{
    public function listing(Organisation $org, Brand $brand): Collection
    {
        return RecurringSchedule::query()->with('target')->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->orderByDesc('id')->limit(100)->get();
    }

    public function find(Organisation $org, Brand $brand, int $id, bool $lock = false): RecurringSchedule
    {
        $query = RecurringSchedule::query()->with('target')->where('organisation_id', $org->id)->where('brand_id', $brand->id)->whereKey($id);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    public function ownsTarget(Organisation $org, Brand $brand, string $targetUuid): ?int
    {
        return ContentTarget::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('uuid', $targetUuid)->value('id');
    }

    public function create(Organisation $org, Brand $brand, int $targetId, int $actorId, array $data): RecurringSchedule
    {
        $row = new RecurringSchedule;
        $row->forceFill(['organisation_id' => $org->id, 'brand_id' => $brand->id,
            'content_target_id' => $targetId, 'created_by_user_id' => $actorId,
            'rrule' => $data['rrule'], 'timezone' => $data['timezone'],
            'start_local' => $data['start_local'], 'end_at' => $data['end_at'],
            'max_occurrences' => $data['max_occurrences'], 'state' => 'ACTIVE', 'version' => 0,
            'next_expand_at' => null])->save();

        return $row->load('target');
    }

    /** A bounded, eventually consistent candidate scan; lock and recheck per schedule. */
    public function dueForProjection(int $limit): array
    {
        return RecurringSchedule::query()->where('state', 'ACTIVE')
            ->where(function ($query): void {
                $query->whereNull('next_expand_at')->orWhere('next_expand_at', '<=', now());
            })->orderBy('id')->limit(max(1, min(100, $limit)))->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function advanceProjection(RecurringSchedule $schedule, string $nextUtc): void
    {
        $schedule->forceFill(['next_expand_at' => $nextUtc])->save();
    }

    public function projectSlot(RecurringSchedule $schedule, array $slot): bool
    {
        $occurrence = RecurringCampaignOccurrence::query()->firstOrCreate([
            'recurring_schedule_id' => $schedule->id, 'occurrence_key' => $slot['occurrence_key'],
        ], [
            'organisation_id' => $schedule->organisation_id, 'brand_id' => $schedule->brand_id,
            'local_at' => $slot['local'],
            'run_at' => CarbonImmutable::parse($slot['run_at'])->utc()->format('Y-m-d H:i:s'),
        ]);

        return $occurrence->wasRecentlyCreated;
    }

    public function markHeldProjection(RecurringSchedule $schedule, array $slot): void
    {
        $this->projectSlot($schedule, $slot);
        RecurringCampaignOccurrence::query()->where('organisation_id', $schedule->organisation_id)
            ->where('brand_id', $schedule->brand_id)->where('recurring_schedule_id', $schedule->id)
            ->where('occurrence_key', $slot['occurrence_key'])->whereNull('held_at')
            ->update(['held_at' => now()]);
    }

    public function projected(Organisation $org, Brand $brand, RecurringSchedule $schedule): Collection
    {
        return RecurringCampaignOccurrence::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('recurring_schedule_id', $schedule->id)
            ->where('run_at', '>=', now()->utc())->orderBy('run_at')->limit(90)->get();
    }

    public function transition(RecurringSchedule $schedule, string $nextState): void
    {
        $schedule->forceFill(['state' => $nextState, 'version' => (int) $schedule->version + 1])->save();
    }
}
