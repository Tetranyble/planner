<?php

namespace App\Repository\Eloquent;

use App\Enums\StateEnum;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\PlannerAlertMailDelivery;
use App\Models\PlannerOperationalAlertEvent;
use App\Models\User;
use App\Repository\Contracts\PlannerAlertMailRepositoryContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Durable tenant-isolated alert notices. Send uncertainty is terminal, never auto-replayed. */
final class PlannerAlertMailRepository implements PlannerAlertMailRepositoryContract
{
    public function queue(int $limit): int
    {
        $queued = 0;
        foreach (PlannerOperationalAlertEvent::query()->whereIn('event_type', ['OPENED', 'REOPENED'])
            ->whereNotIn('id', PlannerAlertMailDelivery::query()->select('alert_event_id'))
            ->orderBy('id')->limit(min(200, max(1, $limit)))->get() as $event) {
            DB::transaction(function () use ($event, &$queued): void {
                $org = Organisation::query()->whereKey($event->organisation_id)->lockForUpdate()->first();
                if (! $org?->isActive() || ! $org->created_by_user_id) {
                    return;
                }
                $owner = User::query()->whereKey($org->created_by_user_id)->whereNotNull('email_verified_at')->first();
                if (! $owner || ! OrganisationMembership::query()->where('organisation_id', $org->id)
                    ->where('user_id', $owner->id)->where('state', StateEnum::ACTIVE->value)->exists()) {
                    return;
                }
                // Recheck the exact alert event's brand relation; no cross-brand messages.
                if (! DB::table('brands')->where('id', $event->brand_id)->where('organisation_id', $org->id)->exists()) {
                    return;
                }
                $row = PlannerAlertMailDelivery::query()->firstOrCreate([
                    'alert_event_id' => $event->id, 'recipient_user_id' => $owner->id,
                ], ['organisation_id' => $org->id, 'brand_id' => $event->brand_id, 'state' => 'PENDING']);
                if ($row->wasRecentlyCreated) {
                    $queued++;
                }
            }, 3);
        }

        return $queued;
    }

    public function quarantineExpired(int $limit): int
    {
        return DB::transaction(function () use ($limit): int {
            $rows = PlannerAlertMailDelivery::query()->where('state', 'LEASED')
                ->where('lease_expires_at', '<', now())->orderBy('id')
                ->limit(max(1, min(1000, $limit)))->lock('FOR UPDATE SKIP LOCKED')->get();
            foreach ($rows as $row) {
                $row->forceFill(['state' => 'UNCERTAIN', 'lease_owner' => null, 'lease_expires_at' => null])->save();
            }

            return $rows->count();
        }, 3);
    }

    public function claim(): ?array
    {
        return DB::transaction(function (): ?array {
            $row = PlannerAlertMailDelivery::query()->where('state', 'PENDING')
                ->orderBy('id')->lock('FOR UPDATE SKIP LOCKED')->first();
            if (! $row) {
                return null;
            }
            $owner = (string) Str::uuid();
            $row->forceFill(['state' => 'LEASED', 'lease_owner' => $owner,
                'lease_expires_at' => now()->addMinutes(5)])->save();

            return ['id' => (int) $row->id, 'owner' => $owner,
                'event_id' => (int) $row->alert_event_id, 'recipient_user_id' => (int) $row->recipient_user_id,
                'organisation_id' => (int) $row->organisation_id, 'brand_id' => (int) $row->brand_id];
        }, 3);
    }

    public function summary(int $organisationId, int $brandId): array
    {
        $counts = PlannerAlertMailDelivery::query()->where('organisation_id', $organisationId)->where('brand_id', $brandId)
            ->selectRaw('state, COUNT(*) as total')->groupBy('state')->pluck('total', 'state');

        return [
            'queued' => (int) ($counts['PENDING'] ?? 0),
            'leased' => (int) ($counts['LEASED'] ?? 0),
            'sent' => (int) ($counts['SENT'] ?? 0),
            'uncertain' => (int) ($counts['UNCERTAIN'] ?? 0),
            'enabled' => (bool) config('planner.operational_alert_mail_enabled', false),
        ];
    }

    public function finish(int $id, string $owner, bool $sent): bool
    {
        return DB::transaction(function () use ($id, $owner, $sent): bool {
            $row = PlannerAlertMailDelivery::query()->whereKey($id)->where('state', 'LEASED')
                ->where('lease_owner', $owner)->lockForUpdate()->first();
            if (! $row) {
                return false;
            }
            $row->forceFill(['state' => $sent ? 'SENT' : 'UNCERTAIN', 'sent_at' => $sent ? now() : null,
                'lease_owner' => null, 'lease_expires_at' => null])->save();

            return true;
        }, 3);
    }
}
