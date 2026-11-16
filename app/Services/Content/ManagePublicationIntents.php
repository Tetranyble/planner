<?php

namespace App\Services\Content;

use App\Authorization\BrandPermissionGuard;
use App\Domain\Content\PublicationIntentRules;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\ContentApprovalRepositoryContract;
use App\Repository\Contracts\ContentStudioRepositoryContract;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Durable operator scheduling intentions, held until a SEPARATE provider dispatcher is verified. */
final class ManagePublicationIntents
{
    public function __construct(
        private readonly BrandPermissionGuard $guard,
        private readonly ContentStudioRepositoryContract $studio,
        private readonly ContentApprovalRepositoryContract $approvals,
        private readonly PublicationIntentRepositoryContract $jobs,
        private readonly AssessPublicationReadiness $readiness,
        private readonly AuditEventRepositoryContract $audit,
    ) {}

    private function authorize(User $actor, Organisation $org, Brand $brand, string $permission): void
    {
        if ((int) $brand->organisation_id !== (int) $org->id || ! $this->guard->allows($actor, $brand, $permission)) {
            throw new AuthorizationException('Publication planning access denied.');
        }
    }

    public function listing(User $actor, Organisation $org, Brand $brand): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.index');

        return $this->jobs->listing($org, $brand, 100)->map(fn ($j) => $this->present($j))->all();
    }

    public function create(User $actor, Organisation $org, Brand $brand, string $itemUuid, string $targetUuid, int $approvalId, string $key, string $runAt): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.store');
        $this->authorize($actor, $org, $brand, 'approval_request.index');
        // Canonicalize an offset-aware timestamp before comparison/idempotency checks.
        if (! preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $runAt)) {
            throw new ConflictHttpException('An explicit schedule timezone is required.');
        }
        try {
            $utc = CarbonImmutable::parse($runAt)->utc();
        } catch (\Throwable) {
            throw new ConflictHttpException('Invalid schedule date and time.');
        }
        if ($utc->lessThanOrEqualTo(now()->addMinutes(5))) {
            throw new ConflictHttpException('Schedule must be at least five minutes in the future.');
        }
        if ($utc->greaterThan(now()->addYear())) {
            throw new ConflictHttpException('Schedule horizon exceeds one year.');
        }

        return DB::transaction(function () use ($actor, $org, $brand, $itemUuid, $targetUuid, $approvalId, $key, $utc) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $locked = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $locked, 'approval_request.store');
            $item = $this->studio->findItem($org, $locked, $itemUuid);
            $target = $this->approvals->target($org, $locked, $item, $targetUuid);
            $approval = $this->approvals->find($org, $locked, $item, $approvalId, true);
            if ((int) $approval->content_target_id !== (int) $target->id) {
                abort(404);
            }
            $existing = $this->jobs->findByOccurrence($org, $locked, $target, $key);
            $runAt = $utc->format('Y-m-d H:i:s');
            if ($existing) {
                if (! PublicationIntentRules::sameIntent($existing->run_at->utc()->format('Y-m-d H:i:s'), (int) $existing->approval_request_id, $runAt, $approvalId)) {
                    throw new ConflictHttpException('Idempotency key already used for different schedule details.');
                }

                return $this->present($existing);
            }
            // Recompute on the write path; the preview is never trusted as a permit.
            $assessment = $this->readiness->assess($actor, $org, $locked, $itemUuid, $targetUuid, $approvalId);
            if (! PublicationIntentRules::canHold($assessment['blockers'])) {
                throw new ConflictHttpException('Cannot plan unapproved, changed, expired or unverified content.');
            }
            $job = $this->jobs->createHeld($org, $locked, $target, $approval, $key, $runAt);
            $this->audit->record($org, $actor, 'publication_intent.held', 'publication_job', (string) $job->id, $locked,
                ['target_uuid' => $targetUuid, 'approval_request_id' => $approvalId, 'run_at' => $utc->toIso8601String()]);

            return $this->present($job);
        }, 3);
    }

    public function cancel(User $actor, Organisation $org, Brand $brand, int $id): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.update');

        return DB::transaction(function () use ($actor, $org, $brand, $id) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $locked = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $locked, 'approval_request.update');
            $job = $this->jobs->findLocked($org, $locked, $id);
            if ($job->state === 'CANCELLED') {
                return $this->present($job);
            }
            if (! in_array($job->state, ['AWAITING_USER', 'SCHEDULED'], true) || $job->lease_owner !== null
                || $job->lease_until !== null || (int) $job->attempt_count !== 0) {
                throw new ConflictHttpException('Only unleased held or not-yet-started scheduled plans can be cancelled.');
            }
            if ($this->jobs->hasUncancelledMediaRun($org, $locked, $job)) {
                throw new ConflictHttpException('Cancel or reconcile the media execution run before cancelling this plan.');
            }
            $this->jobs->cancel($job);
            $this->audit->record($org, $actor, 'publication_intent.cancelled', 'publication_job', (string) $job->id, $locked);

            return $this->present($job);
        }, 3);
    }

    /** Optimistic, audit-backed rescheduling. Never converts a held plan into dispatch permission. */
    public function reschedule(User $actor, Organisation $org, Brand $brand, int $id, string $runAt, int $expectedVersion): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.update');
        $utc = $this->parseRunAt($runAt);

        return DB::transaction(function () use ($actor, $org, $brand, $id, $utc, $expectedVersion) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $locked = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $locked, 'approval_request.update');
            $job = $this->jobs->findLocked($org, $locked, $id);
            if ($job->state !== 'AWAITING_USER' || $job->lease_owner !== null || $job->lease_until !== null) {
                throw new ConflictHttpException('Only unleased, held plans can be rescheduled.');
            }
            if ($this->jobs->hasUncancelledMediaRun($org, $locked, $job)) {
                throw new ConflictHttpException('Media execution run must be resolved before rescheduling.');
            }
            if ((int) $job->version !== $expectedVersion) {
                throw new ConflictHttpException('Plan has changed. Reload before rescheduling.');
            }
            // Revalidation at the plan write boundary is deliberate. Without it an
            // invalidated approval could be pushed forward indefinitely.
            $context = $this->jobs->context($org, $locked, $job);
            $assessment = $this->readiness->assess($actor, $org, $locked,
                $context['item_uuid'], $context['target_uuid'], (int) $job->approval_request_id);
            if (! PublicationIntentRules::canHold($assessment['blockers'])) {
                throw new ConflictHttpException('Content approval or media is no longer eligible for a held plan.');
            }
            $original = $job->run_at->utc()->toIso8601String();
            $this->jobs->reschedule($job, $utc->format('Y-m-d H:i:s'));
            $this->audit->record($org, $actor, 'publication_intent.rescheduled', 'publication_job', (string) $job->id, $locked,
                ['from' => $original, 'to' => $utc->toIso8601String(), 'version' => (int) $job->version]);

            return $this->present($job);
        }, 3);
    }

    /** Atomic cancellation: no partially cancelled selections. */
    public function cancelMany(User $actor, Organisation $org, Brand $brand, array $ids): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.update');
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (count($ids) < 1 || count($ids) > 50 || min($ids) < 1) {
            throw ValidationException::withMessages(['ids' => 'Select 1–50 distinct valid plan IDs.']);
        }
        sort($ids, SORT_NUMERIC);

        return DB::transaction(function () use ($actor, $org, $brand, $ids) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $locked = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $locked, 'approval_request.update');
            $jobs = [];
            foreach ($ids as $id) {
                $job = $this->jobs->findLocked($org, $locked, $id);
                if (! in_array($job->state, ['AWAITING_USER', 'SCHEDULED', 'CANCELLED'], true) || $job->lease_owner !== null || $job->lease_until !== null || (int) $job->attempt_count !== 0) {
                    throw new ConflictHttpException('Selection contains an active or unreconcilable publication. Nothing was cancelled.');
                }
                if ($this->jobs->hasUncancelledMediaRun($org, $locked, $job)) {
                    throw new ConflictHttpException('Selection contains an active media execution run. Nothing was cancelled.');
                }
                $jobs[] = $job;
            }
            $changed = 0;
            foreach ($jobs as $job) {
                if ($job->state === 'CANCELLED') {
                    continue;
                }
                $this->jobs->cancel($job);
                $changed++;
                $this->audit->record($org, $actor, 'publication_intent.cancelled', 'publication_job', (string) $job->id, $locked);
            }

            return ['requested' => count($jobs), 'cancelled' => $changed, 'dispatch_enabled' => false];
        }, 3);
    }

    private function parseRunAt(string $runAt): CarbonImmutable
    {
        if (! preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $runAt)) {
            throw new ConflictHttpException('An explicit schedule timezone is required.');
        }
        try {
            $utc = CarbonImmutable::parse($runAt)->utc();
        } catch (\Throwable) {
            throw new ConflictHttpException('Invalid schedule date and time.');
        }
        if ($utc->lessThanOrEqualTo(now()->addMinutes(5))) {
            throw new ConflictHttpException('Schedule must be at least five minutes in the future.');
        }
        if ($utc->greaterThan(now()->addYear())) {
            throw new ConflictHttpException('Schedule horizon exceeds one year.');
        }

        return $utc;
    }

    private function present($job): array
    {
        return ['id' => (int) $job->id, 'content_target_id' => (int) $job->content_target_id,
            'approval_request_id' => (int) $job->approval_request_id, 'occurrence_key' => $job->occurrence_key,
            'run_at' => $job->run_at->toIso8601String(), 'state' => $job->state,
            'can_dispatch' => false, 'attempt_count' => (int) $job->attempt_count, 'version' => (int) $job->version];
    }
}
