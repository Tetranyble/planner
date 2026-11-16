<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule is application-owned; the outbox job supports retries and expired leases.
Schedule::command('planner:send-organisation-invitations --limit=20')
    ->everyMinute()->withoutOverlapping();

// Short-lived OAuth selection credentials must never accumulate in the database.
Schedule::command('planner:prune-social-oauth-selections')
    ->everyTenMinutes()->withoutOverlapping();

// Short-lived leases protect rotating refresh tokens from concurrent scheduler workers.
Schedule::command('planner:renew-social-credentials --limit=20 --lead-minutes=20')
    ->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Independent of HTTP delivery: accept immediately revokes credentials, worker erases data.
Schedule::command('planner:process-meta-deletions --limit=20')
    ->everyMinute()->withoutOverlapping()->onOneServer();

// Read-only Meta token introspection. Does not grant publishing permissions.
Schedule::command('planner:inspect-meta-connections --limit=20 --minutes=60')
    ->everyFifteenMinutes()->withoutOverlapping()->onOneServer();

// Retention reminders never delete records or renew legal authority automatically.
Schedule::command('planner:queue-privacy-retention-reviews --limit=50')
    ->dailyAt('07:00')->withoutOverlapping()->onOneServer();

// Disabled by default; enable only after production authorization and provider testing.
Schedule::command('planner:process-grant-revocations --limit=20')
    ->everyMinute()->withoutOverlapping()->onOneServer();

// Expire stale human-review requests without generating publication jobs.
Schedule::command('planner:expire-content-approvals --limit=100')
    ->everyTenMinutes()->withoutOverlapping()->onOneServer();

// Held plans are observable, never dispatched. No worker transitions or provider calls.
Schedule::command('planner:inspect-held-publications --limit=100')
    ->everyFifteenMinutes()->withoutOverlapping()->onOneServer();

// Conservative recovery: expired LEASED -> held; expired PUBLISHING -> UNCERTAIN.
// Never calls providers or causes an automatic replay.
Schedule::command('planner:recover-publication-leases --limit=50')
    ->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Offline-only durable harness. Does not mutate publication_jobs or access provider credentials.
Schedule::command('planner:process-publication-sandbox --limit=20')
    ->everyMinute()->withoutOverlapping()->onOneServer();

// Safe scheduled report-only processing, explicitly disabled without environment opt-in.
Schedule::command('planner:process-scheduled-reports --limit=25')
    ->hourly()->withoutOverlapping()->onOneServer();

// Double-locked production execution. Default no-op; never silently released by deployment.
Schedule::command('planner:process-publication-execution --limit=20')
    ->everyMinute()->withoutOverlapping()->onOneServer();

// Worker has independent global + provider + Storage release gates, all default OFF.
Schedule::command('planner:process-media-publications --limit=15')
    ->everyMinute()->withoutOverlapping()->onOneServer();
// Expired send attempts become UNCERTAIN, never automatically replayed.
Schedule::command('planner:recover-media-publication-leases --limit=50')
    ->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Strictly read-only provider analytics. The worker is a no-op until explicitly enabled.
Schedule::command('planner:sync-provider-metrics --limit=20')
    ->everyFifteenMinutes()->withoutOverlapping()->onOneServer();

// Opt-in read-only analytics collection. No posting, no remote mutation.
Schedule::command('planner:queue-provider-metrics --limit=20')
    ->hourly()->withoutOverlapping()->onOneServer();

// Dedicated YouTube resumable worker. Independent flags default OFF. No uploader until Storage verified.
Schedule::command('planner:process-youtube-transfers --limit=10')
    ->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('planner:recover-youtube-transfers --limit=30')
    ->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Bounded credential hygiene; never deletes the audit grant metadata.
Schedule::command('planner:prune-integration-grants --limit=100')
    ->hourly()->withoutOverlapping()->onOneServer();

// Opt-in operational alerts. This worker never edits posts or contacts providers.
Schedule::command('planner:scan-operational-alerts --limit=10000')
    ->dailyAt('06:00')->withoutOverlapping()->onOneServer();

// Uncertain email delivery is never silently replayed. Release independently after SMTP validation.
Schedule::command('planner:send-operational-alerts --limit=30')
    ->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Opt-in two-stage research/resource worker. Only provider API calls; never publishes or retries ambiguous results.
Schedule::command('planner:process-agent-runs --limit=5')
    ->everyMinute()->withoutOverlapping()->onOneServer();

// Metadata-only retention: pending claims 7d, linked provenance 90d; never deletes Storage assets.
Schedule::command('planner:prune-creative-handoffs --limit=100')
    ->dailyAt('03:40')->withoutOverlapping()->onOneServer();

// Optional, idempotent review-only recurrence projection; NEVER creates publication jobs.
Schedule::command('planner:expand-recurring-campaigns --limit=25')
    ->dailyAt('04:00')->withoutOverlapping()->onOneServer();
