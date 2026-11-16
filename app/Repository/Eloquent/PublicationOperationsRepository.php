<?php

namespace App\Repository\Eloquent;

use App\Domain\Publishing\PublicationRecoveryRules;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PublicationAttempt;
use App\Models\PublicationEvent;
use App\Models\PublicationJob;
use App\Models\PublishedPost;
use App\Repository\Contracts\PublicationOperationsRepositoryContract;
use App\Services\Publishing\ExecutePublicationPlans;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** No provider HTTP I/O. Transactions isolate a single preselected job. */
final class PublicationOperationsRepository implements PublicationOperationsRepositoryContract
{
    public function staleLeaseIds(int $limit): array
    {
        return PublicationJob::query()->whereIn('state', ['LEASED', 'PUBLISHING'])
            ->whereNotNull('lease_until')->where('lease_until', '<', now())
            ->orderBy('lease_until')->orderBy('id')->limit($limit)->pluck('id')->all();
    }

    public function recoverStale(int $jobId): ?array
    {
        return DB::transaction(function () use ($jobId) {
            $job = PublicationJob::query()->whereKey($jobId)->lockForUpdate()->first();
            if (! $job) {
                return null;
            }
            $to = PublicationRecoveryRules::recoveryState((string) $job->state,
                $job->lease_until !== null && $job->lease_until->isPast());
            if ($to === null) {
                return null;
            }
            $from = $job->state;
            // In both branches an explicit human decision is required. A LEASED
            // timeout has no recorded provider send; PUBLISHING is ambiguous.
            $job->forceFill(['state' => $to, 'lease_owner' => null, 'lease_until' => null,
                'next_retry_at' => null, 'last_error_code' => 'LEASE_EXPIRED_MANUAL_REVIEW',
                'last_error_message' => null, 'version' => (int) $job->version + 1])->save();
            if ($from === 'PUBLISHING') {
                $attempt = PublicationAttempt::query()->where('organisation_id', $job->organisation_id)
                    ->where('brand_id', $job->brand_id)->where('publication_job_id', $job->id)
                    ->where('result', 'STARTED')->orderByDesc('attempt_number')->lockForUpdate()->first();
                if ($attempt) {
                    $attempt->forceFill(['result' => 'UNCERTAIN', 'finished_at' => now(),
                        'error_code' => 'LEASE_EXPIRED', 'response_redacted' => ['recovery' => 'manual_reconciliation_required']])->save();
                }
            }
            (new PublicationEvent)->forceFill([
                'uuid' => (string) Str::uuid(), 'organisation_id' => $job->organisation_id,
                'brand_id' => $job->brand_id, 'publication_job_id' => $job->id,
                'from_state' => $from, 'to_state' => $to, 'event_type' => 'LEASE_EXPIRED',
                'actor_type' => 'SYSTEM', 'actor_ref' => 'planner:recover-publication-leases',
                'occurred_at' => now(), 'details' => ['manual_review_required' => true, 'provider_replay' => false],
            ])->save();

            return ['id' => $job->id, 'from' => $from, 'to' => $to];
        }, 3);
    }

    public function dashboard(Organisation $organisation, Brand $brand, int $limit): array
    {
        $base = PublicationJob::query()->where('organisation_id', $organisation->id)->where('brand_id', $brand->id);
        $counts = (clone $base)->selectRaw('state, COUNT(*) as total')->groupBy('state')->pluck('total', 'state')->all();
        $items = (clone $base)->orderByDesc('updated_at')->orderByDesc('id')->limit($limit)
            ->get(['id', 'content_target_id', 'approval_request_id', 'state', 'run_at', 'lease_until',
                'attempt_count', 'max_attempts', 'last_error_code', 'updated_at']);
        $confirmed = PublishedPost::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->count();

        return ['counts' => $counts, 'confirmed_published_posts' => $confirmed, 'jobs' => $items->map(fn ($j) => [
            'id' => $j->id, 'content_target_id' => $j->content_target_id,
            'approval_request_id' => $j->approval_request_id, 'state' => $j->state,
            'run_at' => $j->run_at?->toIso8601String(), 'lease_until' => $j->lease_until?->toIso8601String(),
            'attempt_count' => (int) $j->attempt_count, 'max_attempts' => (int) $j->max_attempts,
            'last_error_code' => $j->last_error_code, 'updated_at' => $j->updated_at?->toIso8601String(),
            'requires_reconciliation' => $j->state === 'UNCERTAIN', 'can_dispatch' => false,
        ])->all(), 'dispatch_enabled' => ExecutePublicationPlans::isEnabled()];
    }

    public function timeline(Organisation $organisation, Brand $brand, int $jobId, int $limit): array
    {
        PublicationJob::query()->where('organisation_id', $organisation->id)->where('brand_id', $brand->id)
            ->whereKey($jobId)->firstOrFail();
        $events = PublicationEvent::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('publication_job_id', $jobId)
            ->orderByDesc('id')->limit($limit)->get(['id', 'event_type', 'from_state', 'to_state',
                'actor_type', 'occurred_at']);
        $attempts = PublicationAttempt::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('publication_job_id', $jobId)
            ->orderByDesc('attempt_number')->limit($limit)
            ->get(['id', 'attempt_number', 'result', 'started_at', 'finished_at', 'error_code', 'http_status']);
        $published = PublishedPost::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('publication_job_id', $jobId)
            ->first(['provider', 'external_post_id', 'published_at', 'remote_status']);

        return ['job_id' => $jobId, 'events' => $events, 'attempts' => $attempts,
            'published_post' => $published ? [
                'provider' => $published->provider, 'external_post_id' => $published->external_post_id,
                'published_at' => $published->published_at, 'remote_status' => $published->remote_status,
            ] : null,
            'note' => 'Provider response evidence may be incomplete; no automatic retry of UNCERTAIN records.'];
    }
}
