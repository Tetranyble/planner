<?php

namespace App\Console\Commands;

use App\Enums\StateEnum;
use App\Mail\PlannerOperationalAlertMail;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\PlannerOperationalAlert;
use App\Models\PlannerOperationalAlertEvent;
use App\Models\User;
use App\Repository\Contracts\PlannerAlertMailRepositoryContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

final class DispatchPlannerAlertMail extends Command
{
    protected $signature = 'planner:send-operational-alerts {--limit=30}';

    protected $description = 'Queue and dispatch opt-in operational alert email without automatic replay of ambiguous sends';

    public function handle(PlannerAlertMailRepositoryContract $repo): int
    {
        if (! config('planner.operational_alert_mail_enabled', false)) {
            $this->comment('Operational alert mail is disabled.');

            return self::SUCCESS;
        }
        $limit = max(1, min(100, (int) $this->option('limit')));
        $recovered = $repo->quarantineExpired($limit);
        $queued = $repo->queue($limit);
        $sent = 0;
        $uncertain = 0;
        for ($i = 0; $i < $limit; $i++) {
            $lease = $repo->claim();
            if (! $lease) {
                break;
            }
            $didSend = false;
            try {
                $org = Organisation::query()->whereKey($lease['organisation_id'])->first();
                $brand = Brand::query()->whereKey($lease['brand_id'])->where('organisation_id', $lease['organisation_id'])->first();
                $recipient = User::query()->whereKey($lease['recipient_user_id'])->whereNotNull('email_verified_at')->first();
                $event = PlannerOperationalAlertEvent::query()->whereKey($lease['event_id'])
                    ->where('organisation_id', $lease['organisation_id'])->where('brand_id', $lease['brand_id'])->first();
                if (! $org?->isActive() || ! $brand || ! $recipient || ! $event ||
                    ! OrganisationMembership::query()->where('organisation_id', $org->id)
                        ->where('user_id', $recipient->id)->where('state', StateEnum::ACTIVE->value)->exists()) {
                    // No safe recipient; hold for manual disposition, not replay.
                    throw new \DomainException('alert_context_revoked');
                }
                $alert = PlannerOperationalAlert::query()->whereKey($event->planner_operational_alert_id)
                    ->where('organisation_id', $org->id)->where('brand_id', $brand->id)->firstOrFail();
                Mail::to($recipient->email)->send(new PlannerOperationalAlertMail((string) $brand->name, (string) $alert->signal));
                $didSend = true;
                $sent++;
            } catch (\Throwable) {
                // Provider may have accepted the message. Never guess delivery or auto resend.
                $uncertain++;
            } finally {
                $repo->finish($lease['id'], $lease['owner'], $didSend);
            }
        }
        $this->line(json_encode(['queued' => $queued, 'sent' => $sent, 'uncertain' => $uncertain, 'quarantined_expired_leases' => $recovered], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
