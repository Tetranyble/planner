<?php

namespace App\Services\Scheduling;

use App\Authorization\BrandPermissionGuard;
use App\Domain\Scheduling\RecurringSlotCalculator;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\RecurringSchedule;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use App\Repository\Contracts\RecurringCampaignRepositoryContract;
use App\Services\Content\ManagePublicationIntents;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Versioned recurring templates + operator-confirmed held occurrences.
 * No background scheduler, no automatic approval, no provider delivery.
 */
final class ManageRecurringCampaigns
{
    public function __construct(
        private readonly BrandPermissionGuard $guard,
        private readonly RecurringCampaignRepositoryContract $schedules,
        private readonly PublicationIntentRepositoryContract $jobs,
        private readonly ManagePublicationIntents $intents,
        private readonly AuditEventRepositoryContract $audit,
    ) {}

    private function authorize(User $actor, Organisation $org, Brand $brand, string $permission): void
    {
        if ((int) $brand->organisation_id !== (int) $org->id || ! $this->guard->allows($actor, $brand, $permission)) {
            throw new AuthorizationException('Recurring campaign access denied.');
        }
    }

    public function listing(User $actor, Organisation $org, Brand $brand): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.index');

        return $this->schedules->listing($org, $brand)->map(fn ($s) => $this->present($s))->all();
    }

    public function create(User $actor, Organisation $org, Brand $brand, array $data): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.store');
        $this->authorize($actor, $org, $brand, 'approval_request.index');
        $zone = $this->validatedZone($data['timezone']);
        $start = $data['start_local'];
        $wall = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $start, $zone);
        if (! $wall || $wall->format('Y-m-d H:i:s') !== $start ||
            $wall->lessThanOrEqualTo(CarbonImmutable::now($zone)->addMinutes(5)) ||
            $wall->greaterThan(CarbonImmutable::now($zone)->addYear())) {
            throw ValidationException::withMessages(['start_local' => 'Choose a valid future wall-clock start within one year.']);
        }
        try {
            RecurringSlotCalculator::parse($data['rrule'], (int) $wall->format('N'));
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['rrule' => $e->getMessage()]);
        }
        if (isset($data['end_at'])) {
            try {
                $end = CarbonImmutable::parse($data['end_at'])->utc();
            } catch (\Throwable) {
                throw ValidationException::withMessages(['end_at' => 'Invalid end timestamp.']);
            }
            if (! preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $data['end_at']) ||
                $end->lessThanOrEqualTo($wall->utc()) || $end->greaterThan($wall->utc()->addYear())) {
                throw ValidationException::withMessages(['end_at' => 'End must include a timezone and fall within the one-year schedule.']);
            }
        }
        $data['end_at'] = isset($end) ? $end->format('Y-m-d H:i:s') : null;

        return DB::transaction(function () use ($actor, $org, $brand, $data) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $locked = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $locked, 'approval_request.store');
            $targetId = $this->schedules->ownsTarget($org, $locked, $data['target_uuid']);
            if (! $targetId) {
                abort(404);
            }
            $schedule = $this->schedules->create($org, $locked, $targetId, (int) $actor->id, $data);
            $this->audit->record($org, $actor, 'recurring_campaign.created', 'recurring_schedule', (string) $schedule->id,
                $locked, ['rrule' => $data['rrule'], 'timezone' => $data['timezone'], 'max_occurrences' => $data['max_occurrences']]);

            return $this->present($schedule);
        }, 3);
    }

    public function transition(User $actor, Organisation $org, Brand $brand, int $id, string $state, int $version): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.update');
        if (! in_array($state, ['ACTIVE', 'PAUSED', 'RETIRED'], true)) {
            throw ValidationException::withMessages(['state' => 'Invalid schedule state.']);
        }

        return DB::transaction(function () use ($actor, $org, $brand, $id, $state, $version) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $locked = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $locked, 'approval_request.update');
            $schedule = $this->schedules->find($org, $locked, $id, true);
            if ((int) $schedule->version !== $version) {
                throw new ConflictHttpException('Schedule changed. Reload first.');
            }
            if ($schedule->state === 'RETIRED' && $state !== 'RETIRED') {
                throw new ConflictHttpException('Retired schedules cannot be reactivated.');
            }
            if ($schedule->state !== $state) {
                $this->schedules->transition($schedule, $state);
                $this->audit->record($org, $actor, 'recurring_campaign.state_changed', 'recurring_schedule', (string) $id,
                    $locked, ['state' => $state, 'version' => (int) $schedule->version]);
            }

            return $this->present($schedule);
        }, 3);
    }

    public function preview(User $actor, Organisation $org, Brand $brand, int $id, string $from, string $to): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.index');
        if (! preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $from) || ! preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $to)) {
            throw ValidationException::withMessages(['preview' => 'Preview timestamps require an explicit offset.']);
        }
        $schedule = $this->schedules->find($org, $brand, $id);

        return $this->previewSchedule($schedule, $from, $to);
    }

    /** A single explicit held slot; a recurring template never grants dispatch authority. */
    public function hold(User $actor, Organisation $org, Brand $brand, int $id, array $data): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.store');
        $this->authorize($actor, $org, $brand, 'approval_request.index');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $data['run_at'])) {
            throw ValidationException::withMessages(['run_at' => 'Choose an exact UTC preview slot.']);
        }
        $when = CarbonImmutable::parse($data['run_at'])->utc();

        return DB::transaction(function () use ($actor, $org, $brand, $id, $data, $when) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $locked = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $locked, 'approval_request.store');
            $schedule = $this->schedules->find($org, $locked, $id, true);
            if ($schedule->state !== 'ACTIVE') {
                throw new ConflictHttpException('Schedule is paused or retired.');
            }
            if ((int) $schedule->version !== (int) $data['expected_version']) {
                throw new ConflictHttpException('Schedule changed. Reload before holding.');
            }
            // Recalculate server-side under lock; never trust client-supplied slots or keys.
            $slots = $this->previewSchedule($schedule, $when->subDay()->toIso8601String(), $when->addDay()->toIso8601String())['slots'];
            $slot = null;
            foreach ($slots as $candidate) {
                if ($candidate['run_at'] === $data['run_at']) {
                    $slot = $candidate;
                    break;
                }
            }
            if ($slot === null) {
                throw new ConflictHttpException('Date is not an eligible recurrence occurrence.');
            }
            // The target is immutable and resolves via the canonical approval service.
            $existing = $this->jobs->findByOccurrence($org, $locked, $schedule->target, $slot['occurrence_key']);
            $context = $this->jobs->contextForTarget($org, $locked, (int) $schedule->content_target_id);
            $job = $this->intents->create($actor, $org, $locked,
                $context['item_uuid'], $context['target_uuid'], (int) $data['approval_request_id'],
                $slot['occurrence_key'], $slot['run_at']);
            if ($job['state'] !== 'AWAITING_USER') {
                throw new ConflictHttpException('An occurrence with this key was already cancelled or advanced.');
            }
            // The worker's projection is advisory; only this explicit, approved
            // human action may mark it held. Nothing is armed for delivery.
            $this->schedules->markHeldProjection($schedule, $slot);
            if ($existing === null) {
                $this->audit->record($org, $actor, 'recurring_campaign.occurrence_held', 'recurring_schedule', (string) $id,
                    $locked, ['publication_job_id' => $job['id'], 'run_at' => $slot['run_at']]);
            }

            return ['schedule_id' => $id, 'occurrence' => $slot, 'publication_job' => $job, 'dispatch_enabled' => false];
        }, 3);
    }

    public function projected(User $actor, Organisation $org, Brand $brand, int $id): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.index');
        $schedule = $this->schedules->find($org, $brand, $id);

        return ['schedule' => $this->present($schedule), 'slots' => $this->schedules->projected($org, $brand, $schedule)
            ->map(fn ($row) => ['local' => $row->local_at,
                'run_at' => $row->run_at->utc()->format('Y-m-d\TH:i:s\Z'),
                'occurrence_key' => $row->occurrence_key, 'held' => $row->held_at !== null])->all(),
            'dispatch_enabled' => false, 'projection_only' => true];
    }

    private function previewSchedule(RecurringSchedule $schedule, string $from, string $to): array
    {
        try {
            $range = RecurringSlotCalculator::expand((int) $schedule->id, (string) $schedule->rrule,
                (string) $schedule->timezone, (string) $schedule->getRawOriginal('start_local'),
                $from, $to, (int) $schedule->max_occurrences,
                $schedule->end_at ? CarbonImmutable::parse($schedule->getRawOriginal('end_at'), 'UTC')->toIso8601String() : null);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['preview' => $e->getMessage()]);
        }

        return ['schedule' => $this->present($schedule), ...$range, 'dispatch_enabled' => false,
            'note' => 'Preview only. Each occurrence needs a valid existing approval and an explicit operator hold.'];
    }

    private function validatedZone(string $timezone): DateTimeZone
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true) && $timezone !== 'UTC') {
            throw ValidationException::withMessages(['timezone' => 'Choose an IANA timezone.']);
        }

        return new DateTimeZone($timezone);
    }

    private function present(RecurringSchedule $row): array
    {
        return ['id' => (int) $row->id, 'target_id' => (int) $row->content_target_id, 'target_uuid' => (string) $row->target?->uuid,
            'rrule' => $row->rrule, 'timezone' => $row->timezone,
            'start_local' => $row->getRawOriginal('start_local'),
            'end_at' => $row->end_at, 'max_occurrences' => (int) $row->max_occurrences,
            'state' => $row->state, 'version' => (int) $row->version,
            'next_expand_at' => $row->next_expand_at, 'dispatch_enabled' => false];
    }
}
