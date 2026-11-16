<?php

namespace App\Repository\Eloquent;

use App\Domain\Privacy\MetaReviewDisposition;
use App\Enums\StateEnum;
use App\Models\MetaDataDeletionRequest;
use App\Models\MetaDataDeletionReviewDecision;
use App\Models\MetaDataDeletionTarget;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\User;
use App\Repository\Contracts\MetaDeletionReviewRepositoryContract;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class MetaDeletionReviewRepository implements MetaDeletionReviewRepositoryContract
{
    public function isActiveOwner(Organisation $organisation, User $actor): bool
    {
        return OrganisationMembership::query()
            ->join('roles', 'roles.id', '=', 'organisation_memberships.role_id')
            ->where('organisation_memberships.organisation_id', $organisation->id)
            ->where('organisation_memberships.user_id', $actor->id)
            ->where('organisation_memberships.state', StateEnum::ACTIVE->value)
            ->where('roles.name', 'owner')->exists();
    }

    public function directory(Organisation $organisation): array
    {
        return MetaDataDeletionTarget::query()->where('organisation_id', $organisation->id)
            ->where('state', 'NEEDS_REVIEW')->orderBy('created_at')
            ->limit(100)->get(['id', 'request_id', 'state', 'review_reason', 'processed_at', 'created_at'])
            ->map(fn ($row) => [
                'id' => $row->id, 'state' => $row->state, 'review_reason' => $row->review_reason,
                'created_at' => $row->created_at?->toIso8601String(),
                // Never expose the provider's app-scoped actor, other tenants, or confirmation code.
            ])->all();
    }

    public function lockReviewTarget(Organisation $organisation, int $targetId): MetaDataDeletionTarget
    {
        $scope = MetaDataDeletionTarget::query()->where('organisation_id', $organisation->id)
            ->whereKey($targetId)->firstOrFail();
        MetaDataDeletionRequest::query()->whereKey($scope->request_id)->lockForUpdate()->firstOrFail();

        return MetaDataDeletionTarget::query()->where('organisation_id', $organisation->id)
            ->whereKey($targetId)->lockForUpdate()->firstOrFail();
    }

    public function resolve(MetaDataDeletionTarget $target, User $reviewer, array $decision): void
    {
        if ($target->state !== 'NEEDS_REVIEW') {
            throw new ConflictHttpException('This item is not awaiting privacy review.');
        }
        $connection = ProviderConnection::query()->where('organisation_id', $target->organisation_id)
            ->whereKey($target->provider_connection_id)->lockForUpdate()->firstOrFail();
        if ($connection->status === 'CONNECTED'
            || ProviderConnectionCredential::query()->where('organisation_id', $target->organisation_id)
                ->where('provider_connection_id', $connection->id)->exists()) {
            // An officer cannot override an active/reusable grant with a paper review.
            throw new ConflictHttpException('Provider authorization is still active; revoke and erase credentials first.');
        }
        MetaDataDeletionReviewDecision::query()->create([
            'organisation_id' => $target->organisation_id,
            'target_id' => $target->id,
            'reviewed_by_user_id' => $reviewer->id,
            'disposition' => $decision['disposition'],
            'legal_basis' => $decision['legalBasis'] ?: null,
            'explanation' => $decision['notes'],
            'evidence_reference' => $decision['evidence'],
            'review_again_at' => $decision['reviewAt'] ?: null,
        ]);
        $target->forceFill(['state' => $decision['disposition'] === MetaReviewDisposition::RETAINED_LEGAL
            ? 'RETAINED_LEGAL' : 'COMPLETED', 'review_reason' => null, 'processed_at' => now()])->save();
        // Only close the request once every target has a final decision.
        $request = MetaDataDeletionRequest::query()->whereKey($target->request_id)->lockForUpdate()->firstOrFail();
        $total = MetaDataDeletionTarget::query()->where('request_id', $request->id)->count();
        $resolved = MetaDataDeletionTarget::query()->where('request_id', $request->id)
            ->whereIn('state', ['COMPLETED', 'RETAINED_LEGAL'])->count();
        if ($total > 0 && $resolved === $total) {
            $retained = MetaDataDeletionTarget::query()->where('request_id', $request->id)
                ->where('state', 'RETAINED_LEGAL')->exists();
            $request->forceFill([
                'processed_grants' => $resolved,
                'state' => $retained ? 'COMPLETED_WITH_RETENTION' : 'COMPLETED',
                'last_error_code' => $retained ? 'LAWFUL_RETENTION_RECORDED' : null,
                'completed_at' => now(),
            ])->save();
        } else {
            $request->forceFill(['processed_grants' => $resolved])->save();
        }
    }
}
