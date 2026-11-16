<?php

namespace App\Console\Commands;

use App\Mail\OrganisationInvitationMail;
use App\Models\OrganisationInvitation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/** Durable, bounded, recoverable email dispatcher backed by the existing outbox. */
final class DispatchOrganisationInvitationMail extends Command
{
    protected $signature = 'planner:send-organisation-invitations {--limit=20}';

    protected $description = 'Deliver pending organisation invitation emails from the transactional outbox';

    public function handle(): int
    {
        $limit = max(1, min(100, (int) $this->option('limit')));
        $sent = 0;
        for ($i = 0; $i < $limit; $i++) {
            $event = DB::transaction(function (): ?object {
                $row = DB::table('outbox_events')
                    ->where('event_type', 'organisation.invitation.created')
                    ->where(function ($q): void {
                        $q->where('state', 'PENDING')
                            ->orWhere(fn ($leased) => $leased->where('state', 'LEASED')->where('lease_until', '<', now()));
                    })
                    ->where(fn ($q) => $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))
                    ->orderBy('id')->lock('FOR UPDATE SKIP LOCKED')->first();
                if ($row === null) {
                    return null;
                }
                DB::table('outbox_events')->where('id', $row->id)->update([
                    'state' => 'LEASED',
                    'lease_owner' => (string) Str::uuid(),
                    'lease_until' => now()->addMinutes(5),
                    'attempts' => $row->attempts + 1,
                    'updated_at' => now(),
                ]);

                return $row;
            }, attempts: 3);
            if ($event === null) {
                break;
            }

            try {
                $payload = json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR);
                $invitation = OrganisationInvitation::query()->with('organisation')->findOrFail($payload['invitation_id']);
                if ($invitation->accepted_at === null && $invitation->revoked_at === null
                    && $invitation->expires_at->isFuture() && $invitation->organisation->isActive()) {
                    $token = Crypt::decryptString($payload['encrypted_token']);
                    // Verify the token still matches the stored hash even if an event was tampered with.
                    if (! hash_equals($invitation->token_hash, hash('sha256', $token))) {
                        throw new \RuntimeException('Invitation token integrity check failed.');
                    }
                    Mail::to($invitation->email)->send(new OrganisationInvitationMail(
                        $invitation->organisation->name,
                        route('organisation.invitations.show', ['token' => $token]),
                    ));
                    $sent++;
                }
                DB::table('outbox_events')->where('id', $event->id)->update([
                    'state' => 'DISPATCHED', 'processed_at' => now(),
                    'lease_owner' => null, 'lease_until' => null, 'updated_at' => now(),
                ]);
            } catch (Throwable $exception) {
                // Do not record the payload, email, decrypted token or URL in logs.
                report(new \RuntimeException('Invitation mail delivery failed for outbox id '.$event->id.' ('.get_class($exception).')'));
                $attempts = (int) $event->attempts + 1;
                DB::table('outbox_events')->where('id', $event->id)->update([
                    'state' => $attempts >= 6 ? 'FAILED' : 'PENDING',
                    'next_retry_at' => $attempts >= 6 ? null : now()->addSeconds(min(3600, 30 * (2 ** $attempts))),
                    'lease_owner' => null, 'lease_until' => null, 'updated_at' => now(),
                ]);
            }
        }
        $this->components->info("Dispatched {$sent} invitation email(s).");

        return self::SUCCESS;
    }
}
