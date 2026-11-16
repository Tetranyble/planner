<?php

namespace App\Repository\Eloquent;

use App\Models\MetaDataDeletionReviewDecision;
use App\Models\Organisation;
use App\Models\PrivacyRetentionFollowup;
use App\Models\User;
use App\Repository\Contracts\PrivacyRetentionFollowupRepositoryContract;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PrivacyRetentionFollowupRepository implements PrivacyRetentionFollowupRepositoryContract
{
    public function enqueueDue(int $limit): int
    {
        $decisions = MetaDataDeletionReviewDecision::query()
            ->join('meta_data_deletion_targets as t', function ($join): void {
                $join->on('t.id', '=', 'meta_data_deletion_review_decisions.target_id')
                    ->on('t.organisation_id', '=', 'meta_data_deletion_review_decisions.organisation_id');
            })
            ->where('meta_data_deletion_review_decisions.disposition', 'RETAINED_LEGAL')
            ->where('t.state', 'RETAINED_LEGAL')
            ->whereNotNull('meta_data_deletion_review_decisions.review_again_at')
            ->where('meta_data_deletion_review_decisions.review_again_at', '<=', now())
            ->whereNotIn('meta_data_deletion_review_decisions.id', function ($query): void {
                $query->select('review_decision_id')->from('privacy_retention_followups');
            })
            ->orderBy('meta_data_deletion_review_decisions.review_again_at')
            ->limit(max(1, min($limit, 200)))
            ->get(['meta_data_deletion_review_decisions.id', 'meta_data_deletion_review_decisions.organisation_id',
                'meta_data_deletion_review_decisions.review_again_at']);
        $created = 0;
        foreach ($decisions as $decision) {
            try {
                PrivacyRetentionFollowup::query()->create([
                    'organisation_id' => $decision->organisation_id,
                    'review_decision_id' => $decision->id,
                    'due_at' => $decision->review_again_at,
                    'status' => 'DUE',
                ]);
                $created++;
            } catch (UniqueConstraintViolationException) {
                // Another worker queued this same decision. Preserve all other SQL failures.
            }
        }

        return $created;
    }

    public function directory(Organisation $organisation): array
    {
        return PrivacyRetentionFollowup::query()->where('organisation_id', $organisation->id)
            ->where('status', 'DUE')->orderBy('due_at')->limit(100)->get()
            ->map(fn ($r): array => [
                'id' => (int) $r->id,
                'due_at' => $r->due_at?->toIso8601String(),
                'status' => $r->status,
                'action_required' => 'Re-evaluate lawful retention and supporting evidence; acknowledgement is not erasure.',
            ])->all();
    }

    public function acknowledge(Organisation $organisation, User $actor, int $id, string $notes): void
    {
        DB::transaction(function () use ($organisation, $actor, $id, $notes): void {
            $row = PrivacyRetentionFollowup::query()->where('organisation_id', $organisation->id)
                ->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($row->status !== 'DUE') {
                throw new ConflictHttpException('Follow-up was already acknowledged.');
            }
            $row->forceFill(['status' => 'ACKNOWLEDGED', 'acknowledged_by_user_id' => $actor->id,
                'acknowledged_at' => now(), 'acknowledgement_notes' => $notes])->save();
        }, attempts: 3);
    }
}
