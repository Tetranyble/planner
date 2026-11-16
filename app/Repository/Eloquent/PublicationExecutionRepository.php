<?php

namespace App\Repository\Eloquent;

use App\Domain\Publishing\Delivery\DispatchOutcome;
use App\Models\Brand;
use App\Models\ContentTarget;
use App\Models\Organisation;
use App\Models\PublicationAttempt;
use App\Models\PublicationEvent;
use App\Models\PublicationJob;
use App\Models\PublishedPost;
use App\Repository\Contracts\PublicationExecutionRepositoryContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Publication transitions and attempt persistence live in this repository only. Never perform HTTP in a DB lock. */
final class PublicationExecutionRepository implements PublicationExecutionRepositoryContract
{
    public function arm(Organisation $organisation, Brand $brand, PublicationJob $job, int $actorId, int $expectedVersion): PublicationJob
    {
        if ((int) $job->organisation_id !== (int) $organisation->id || (int) $job->brand_id !== (int) $brand->id
            || $job->state !== 'AWAITING_USER' || $job->lease_owner !== null || (int) $job->version !== $expectedVersion) {
            throw new ConflictHttpException('Publication changed, was leased or is no longer held.');
        }
        if ((int) $job->attempt_count !== 0 || PublicationAttempt::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('publication_job_id', $job->id)->exists()) {
            throw new ConflictHttpException('Previously attempted publication cannot be armed again.');
        }
        $from = (string) $job->state;
        $job->forceFill(['state' => 'SCHEDULED', 'armed_by_user_id' => $actorId, 'armed_at' => now(),
            'attempt_count' => 0, 'max_attempts' => 1, 'next_retry_at' => null, 'version' => (int) $job->version + 1])->save();
        $this->event($job, $from, 'SCHEDULED', 'OPERATOR_ARMED', 'USER', (string) $actorId,
            ['automatic_retry' => false, 'max_attempts' => 1]);

        return $job;
    }

    public function dueIds(int $limit): array
    {
        return PublicationJob::query()->where('state', 'SCHEDULED')->where('run_at', '<=', now())
            ->orderBy('run_at')->orderBy('id')->limit(max(1, min(100, $limit)))->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function claim(int $jobId, string $leaseOwner): ?array
    {
        return DB::transaction(function () use ($jobId, $leaseOwner) {
            $job = PublicationJob::query()->whereKey($jobId)->lockForUpdate()->first();
            if (! $job || $job->state !== 'SCHEDULED' || $job->run_at->isFuture()
                || (int) $job->max_attempts !== 1 || (int) $job->attempt_count !== 0
                || $job->lease_owner !== null || ! $job->armed_by_user_id) {
                return null;
            }
            $job->forceFill(['state' => 'LEASED', 'lease_owner' => $leaseOwner, 'lease_until' => now()->addMinutes(2),
                'version' => (int) $job->version + 1])->save();
            $this->event($job, 'SCHEDULED', 'LEASED', 'WORKER_CLAIMED', 'SYSTEM', 'planner:process-publication-execution');

            return ['id' => (int) $job->id, 'owner' => $leaseOwner, 'organisation_id' => (int) $job->organisation_id,
                'brand_id' => (int) $job->brand_id, 'armed_by_user_id' => (int) $job->armed_by_user_id];
        }, 3);
    }

    public function scopedLease(int $jobId, string $owner): ?array
    {
        $job = PublicationJob::query()->whereKey($jobId)->where('state', 'LEASED')
            ->where('lease_owner', $owner)->where('lease_until', '>', now())->first();
        if (! $job) {
            return null;
        }
        $org = Organisation::query()->whereKey($job->organisation_id)->first();
        $brand = Brand::query()->where('organisation_id', $job->organisation_id)->whereKey($job->brand_id)->first();

        return $org && $brand ? ['job' => $job, 'organisation' => $org, 'brand' => $brand] : null;
    }

    public function holdBlocked(int $jobId, string $owner, string $code): bool
    {
        return DB::transaction(function () use ($jobId, $owner, $code) {
            $job = $this->lockedLease($jobId, $owner, 'LEASED');
            if (! $job) {
                return false;
            }
            $job->forceFill(['state' => 'AWAITING_USER', 'lease_owner' => null, 'lease_until' => null,
                'last_error_code' => $this->safeCode($code), 'last_error_message' => null,
                'version' => (int) $job->version + 1])->save();
            $this->event($job, 'LEASED', 'AWAITING_USER', 'PREFLIGHT_BLOCKED', 'SYSTEM', null,
                ['code' => $this->safeCode($code), 'sent' => false]);

            return true;
        }, 3);
    }

    public function startAttempt(int $jobId, string $owner): ?string
    {
        return DB::transaction(function () use ($jobId, $owner) {
            $job = $this->lockedLease($jobId, $owner, 'LEASED');
            if (! $job || (int) $job->attempt_count !== 0 || (int) $job->max_attempts !== 1) {
                return null;
            }
            if (PublicationAttempt::query()->where('organisation_id', $job->organisation_id)
                ->where('brand_id', $job->brand_id)->where('publication_job_id', $job->id)->exists()) {
                return null;
            }
            $operation = hash('sha256', 'planner:publication:v1:'.$job->organisation_id.':'.$job->id.':'.$job->occurrence_key);
            (new PublicationAttempt)->forceFill([
                'uuid' => (string) Str::uuid(), 'organisation_id' => $job->organisation_id, 'brand_id' => $job->brand_id,
                'publication_job_id' => $job->id, 'attempt_number' => 1, 'operation_key' => $operation,
                'result' => 'STARTED', 'started_at' => now(), 'response_redacted' => ['status' => 'IN_FLIGHT'],
            ])->save();
            $job->forceFill(['state' => 'PUBLISHING', 'attempt_count' => 1, 'version' => (int) $job->version + 1])->save();
            $this->event($job, 'LEASED', 'PUBLISHING', 'REQUEST_STARTED', 'SYSTEM', null,
                ['operation_key_digest' => substr($operation, 0, 16), 'automatic_retry' => false]);

            return $operation;
        }, 3);
    }

    public function attemptIsFenced(int $jobId, string $owner, string $operationKey): bool
    {
        $job = PublicationJob::query()->whereKey($jobId)->where('state', 'PUBLISHING')
            ->where('lease_owner', $owner)->where('lease_until', '>', now())
            ->where('attempt_count', 1)->where('max_attempts', 1)
            ->where('run_at', '<=', now())->first();

        return $job !== null && PublicationAttempt::query()->where('organisation_id', $job->organisation_id)
            ->where('brand_id', $job->brand_id)->where('publication_job_id', $job->id)
            ->where('operation_key', $operationKey)->where('attempt_number', 1)->where('result', 'STARTED')->exists();
    }

    public function finishAttempt(int $jobId, string $owner, string $operationKey, DispatchOutcome $outcome): bool
    {
        return DB::transaction(function () use ($jobId, $owner, $operationKey, $outcome) {
            $job = $this->lockedLease($jobId, $owner, 'PUBLISHING');
            if (! $job) {
                return false;
            } // Lease stolen / recovery completed; never overwrite it.
            $attempt = PublicationAttempt::query()->where('organisation_id', $job->organisation_id)
                ->where('brand_id', $job->brand_id)->where('publication_job_id', $job->id)
                ->where('operation_key', $operationKey)->where('result', 'STARTED')->lockForUpdate()->first();
            if (! $attempt) {
                return false;
            }
            // A provider receipt is not fully recorded until the tenant-scoped published-post
            // ledger commits in the SAME transaction as this attempt/job transition.
            if ($outcome->kind === 'SUCCESS') {
                $target = ContentTarget::query()->where('organisation_id', $job->organisation_id)
                    ->where('brand_id', $job->brand_id)->whereKey($job->content_target_id)->firstOrFail();
                $account = $target->account()->where('organisation_id', $job->organisation_id)
                    ->where('brand_id', $job->brand_id)->firstOrFail();
                $receipt = (string) $outcome->providerReceipt;
                $existing = PublishedPost::query()->where('organisation_id', $job->organisation_id)
                    ->where('social_account_id', $account->id)->where('external_post_id', $receipt)->lockForUpdate()->first();
                if ($existing && (int) $existing->publication_job_id !== (int) $job->id) {
                    // Duplicate receipts across jobs need manual reconciliation, never a false success.
                    $outcome = new DispatchOutcome('UNCERTAIN', 'RECEIPT_COLLISION', $outcome->httpStatus);
                } elseif (! $existing) {
                    (new PublishedPost)->forceFill([
                        'organisation_id' => $job->organisation_id, 'brand_id' => $job->brand_id,
                        'publication_job_id' => $job->id, 'social_account_id' => $account->id,
                        'provider' => $account->provider, 'external_post_id' => $receipt,
                        'published_at' => now(), 'remote_status' => 'PUBLISHED',
                    ])->save();
                }
            }
            // No automatic retry following any initiated HTTP send, even for rate-limits.
            $state = match ($outcome->kind) {
                'SUCCESS' => 'PUBLISHED','PERMANENT_FAILURE' => 'FAILED','RETRYABLE','RATE_LIMITED','UNCERTAIN' => 'UNCERTAIN',
                default => 'UNCERTAIN',
            };
            $result = match ($state) {
                'PUBLISHED' => 'SUCCESS','FAILED' => 'FAILED',default => 'UNCERTAIN'
            };
            $attempt->forceFill(['result' => $result, 'finished_at' => now(), 'http_status' => $outcome->httpStatus,
                'error_code' => $this->safeCode($outcome->code), 'provider_request_id' => $outcome->providerReceipt,
                'response_redacted' => $outcome->redacted()])->save();
            $job->forceFill(['state' => $state, 'lease_owner' => null, 'lease_until' => null,
                'next_retry_at' => null, 'last_error_code' => $state === 'PUBLISHED' ? null : $this->safeCode($outcome->code),
                'last_error_message' => null, 'version' => (int) $job->version + 1])->save();
            $this->event($job, 'PUBLISHING', $state, 'PROVIDER_OUTCOME', 'SYSTEM', null,
                ['kind' => $outcome->kind, 'code' => $this->safeCode($outcome->code), 'has_receipt' => $outcome->providerReceipt !== null]);

            return true;
        }, 3);
    }

    private function lockedLease(int $id, string $owner, string $state): ?PublicationJob
    {
        return PublicationJob::query()->whereKey($id)->where('state', $state)
            ->where('lease_owner', $owner)->where('lease_until', '>', now())->lockForUpdate()->first();
    }

    private function safeCode(string $code): string
    {
        return preg_match('/^[A-Z0-9_]{2,60}$/D', $code) ? $code : 'EXECUTION_ERROR';
    }

    private function event(PublicationJob $job, ?string $from, string $to, string $type, string $actorType, ?string $actorRef = null, array $details = []): void
    {
        (new PublicationEvent)->forceFill(['uuid' => (string) Str::uuid(), 'organisation_id' => $job->organisation_id,
            'brand_id' => $job->brand_id, 'publication_job_id' => $job->id, 'from_state' => $from, 'to_state' => $to,
            'event_type' => $type, 'actor_type' => $actorType, 'actor_ref' => $actorRef,
            'occurred_at' => now(), 'details' => $details])->save();
    }
}
