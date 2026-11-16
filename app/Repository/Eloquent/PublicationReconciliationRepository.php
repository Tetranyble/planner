<?php

namespace App\Repository\Eloquent;

use App\Domain\Publishing\PublicationReconciliationRules as Rules;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PublicationEvent;
use App\Models\PublicationJob;
use App\Models\PublicationReconciliationDecision;
use App\Models\PublicationReconciliationEvidence;
use App\Repository\Contracts\PublicationReconciliationRepositoryContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** All writes lock the tenant-scoped job. Human attestations are NOT provider verification. */
final class PublicationReconciliationRepository implements PublicationReconciliationRepositoryContract
{
    private function job(Organisation $org, Brand $brand, int $id, bool $lock = false): PublicationJob
    {
        $q = PublicationJob::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)->whereKey($id);

        return ($lock ? $q->lockForUpdate() : $q)->firstOrFail();
    }

    public function history(Organisation $org, Brand $brand, int $jobId): array
    {
        $this->job($org, $brand, $jobId);
        $evidence = PublicationReconciliationEvidence::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)->where('publication_job_id', $jobId);
        $decision = PublicationReconciliationDecision::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)->where('publication_job_id', $jobId);

        return ['evidence' => $evidence->orderByDesc('id')->get(['id', 'evidence_kind', 'external_reference', 'evidence_sha256', 'summary', 'submitted_by', 'created_at'])->all(),
            'decision' => $decision->first(['id', 'evidence_id', 'disposition', 'rationale', 'reviewed_by', 'created_at']),
            'remote_dispatch_enabled' => false];
    }

    public function submit(Organisation $org, Brand $brand, int $jobId, int $actorId, array $payload): array
    {
        return DB::transaction(function () use ($org, $brand, $jobId, $actorId, $payload) {
            $job = $this->job($org, $brand, $jobId, true);
            if ($job->state !== 'UNCERTAIN') {
                throw new ConflictHttpException('Only uncertain publications accept reconciliation evidence.');
            }
            if (PublicationReconciliationDecision::query()->where('publication_job_id', $jobId)->exists()) {
                throw new ConflictHttpException('Reconciliation is finalized.');
            }
            $evidence = (new PublicationReconciliationEvidence)->forceFill([
                'organisation_id' => $org->id, 'brand_id' => $brand->id, 'publication_job_id' => $jobId,
                'submitted_by' => $actorId, 'evidence_kind' => $payload['evidence_kind'],
                'external_reference' => $payload['external_reference'] ?? null, 'evidence_sha256' => strtolower($payload['evidence_sha256']),
                'summary' => $payload['summary']]);
            $evidence->save();
            $this->event($job, 'RECONCILIATION_EVIDENCE', $actorId, ['evidence_id' => $evidence->id, 'kind' => $payload['evidence_kind']]);

            return ['id' => $evidence->id, 'status' => 'RECORDED', 'remote_dispatch_enabled' => false];
        }, 3);
    }

    public function decide(Organisation $org, Brand $brand, int $jobId, int $actorId, array $payload): array
    {
        return DB::transaction(function () use ($org, $brand, $jobId, $actorId, $payload) {
            $job = $this->job($org, $brand, $jobId, true);
            $existing = PublicationReconciliationDecision::query()->where('publication_job_id', $jobId)->exists();
            $evidence = PublicationReconciliationEvidence::query()->where('organisation_id', $org->id)
                ->where('brand_id', $brand->id)->where('publication_job_id', $jobId)->where('id', $payload['evidence_id'])->first();
            if (! $evidence) {
                throw new ConflictHttpException('Evidence does not belong to this publication.');
            }
            if (! Rules::canReview($job->state, (int) $evidence->submitted_by, $actorId, $existing)) {
                throw new ConflictHttpException('Independent review required for an unresolved uncertain publication.');
            }
            $decision = (new PublicationReconciliationDecision)->forceFill([
                'organisation_id' => $org->id, 'brand_id' => $brand->id, 'publication_job_id' => $jobId,
                'reviewed_by' => $actorId, 'evidence_id' => $evidence->id, 'disposition' => $payload['disposition'],
                'rationale' => $payload['rationale']]);
            $decision->save();
            // Preserve UNCERTAIN: provider state is not inferred from human-provided evidence.
            $this->event($job, 'RECONCILIATION_REVIEW', $actorId, ['decision_id' => $decision->id, 'disposition' => $payload['disposition'], 'no_automatic_replay' => true]);

            return ['id' => $decision->id, 'disposition' => $payload['disposition'], 'publication_state' => 'UNCERTAIN', 'remote_dispatch_enabled' => false];
        }, 3);
    }

    private function event(PublicationJob $job, string $type, int $actorId, array $details): void
    {
        (new PublicationEvent)->forceFill(['uuid' => (string) Str::uuid(), 'organisation_id' => $job->organisation_id,
            'brand_id' => $job->brand_id, 'publication_job_id' => $job->id, 'from_state' => $job->state, 'to_state' => $job->state,
            'event_type' => $type, 'actor_type' => 'USER', 'actor_ref' => (string) $actorId, 'occurred_at' => now(), 'details' => $details])->save();
    }
}
