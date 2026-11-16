<?php

namespace App\Services\Content;

use App\Authorization\BrandPermissionGuard;
use App\Domain\Content\PublicationIntentRules;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Read-only, bounded review of persisted holds. This is never a dispatch permit. */
final class InspectHeldPublicationPlans
{
    public function __construct(
        private readonly BrandPermissionGuard $guard,
        private readonly PublicationIntentRepositoryContract $jobs,
        private readonly AssessPublicationReadiness $readiness,
    ) {}

    public function inspect(User $actor, Organisation $org, Brand $brand, array $jobIds): array
    {
        if ((int) $brand->organisation_id !== (int) $org->id ||
            ! $this->guard->allows($actor, $brand, 'approval_request.index')) {
            throw new AuthorizationException('Publication inspection denied.');
        }
        $jobIds = array_values(array_unique(array_map('intval', $jobIds)));
        if (! $jobIds || count($jobIds) > 50 || min($jobIds) < 1) {
            throw ValidationException::withMessages([
                'ids' => 'Select between 1 and 50 valid publication plan IDs.',
            ]);
        }
        $results = [];
        foreach ($jobIds as $id) {
            // Always enforce organisation and brand on every lookup. Missing or
            // cross-tenant IDs produce the same 404, never a partial report.
            $job = $this->jobs->findForInspection($org, $brand, $id);
            $base = [
                'job_id' => (int) $job->id,
                'job_version' => (int) $job->version,
                'state' => (string) $job->state,
                'run_at' => $job->run_at?->toIso8601String(),
                'can_dispatch' => false,
            ];
            if ($job->state !== 'AWAITING_USER') {
                $results[] = $base + [
                    'assessment' => 'NOT_HELD',
                    'blockers' => ['JOB_NOT_HELD'],
                ];

                continue;
            }
            try {
                $context = $this->jobs->context($org, $brand, $job);
                $assessment = $this->readiness->assess(
                    $actor, $org, $brand, $context['item_uuid'],
                    $context['target_uuid'], (int) $job->approval_request_id,
                );
                $hard = array_values(array_intersect(
                    $assessment['blockers'], PublicationIntentRules::hardBlockers()
                ));
                $results[] = $base + [
                    'assessment' => $hard ? 'REQUIRES_REVIEW' : 'HOLD_ELIGIBLE',
                    'blockers' => $assessment['blockers'],
                    'hard_blockers' => $hard,
                ];
            } catch (AuthorizationException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                // No exception details or tenant identifiers are leaked.
                // Fail closed on missing/deleted media, targets or approvals.
                $results[] = $base + [
                    'assessment' => 'REQUIRES_REVIEW',
                    'blockers' => ['EVIDENCE_UNAVAILABLE'],
                    'hard_blockers' => ['EVIDENCE_UNAVAILABLE'],
                ];
            }
        }

        return [
            'checked_at' => now()->toIso8601String(),
            'items' => $results,
            'summary' => [
                'inspected' => count($results),
                'hold_eligible' => count(array_filter($results, fn ($r) => $r['assessment'] === 'HOLD_ELIGIBLE')),
                'requires_review' => count(array_filter($results, fn ($r) => $r['assessment'] === 'REQUIRES_REVIEW')),
                'not_held' => count(array_filter($results, fn ($r) => $r['assessment'] === 'NOT_HELD')),
            ],
            'publishing_enabled' => false,
        ];
    }
}
