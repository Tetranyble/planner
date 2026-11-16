<?php

namespace App\Repository\Eloquent;

use App\Enums\ApprovalPolicyState;
use App\Enums\ClaimStatus;
use App\Enums\EvidenceReviewState;
use App\Enums\TrustStatus;
use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\BrandClaim;
use App\Models\BrandClaimEvidence;
use App\Models\BrandClaimPolicy;
use App\Models\BrandClaimReviewEvent;
use App\Models\BrandClaimReviewEvidence;
use App\Models\BrandKnowledgeSource;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\BrandGovernanceRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class BrandGovernanceRepository implements BrandGovernanceRepositoryContract
{
    private function scope(string $model, Organisation $organisation, Brand $brand): Builder
    {
        return $model::query()->where('organisation_id', $organisation->getKey())
            ->where('brand_id', $brand->getKey());
    }

    public function createSource(Organisation $organisation, Brand $brand, User $actor, array $data): BrandKnowledgeSource
    {
        $content = trim((string) ($data['content_text'] ?? ''));
        $url = trim((string) ($data['source_url'] ?? ''));
        $hash = hash('sha256', implode("\n", [$data['source_type'], $url, $content]));
        $existing = $this->scope(BrandKnowledgeSource::class, $organisation, $brand)
            ->where('content_hash', $hash)->first();
        if ($existing) {
            throw ValidationException::withMessages(['content_text' => 'An identical source already exists for this brand.']);
        }
        $row = new BrandKnowledgeSource;
        $row->forceFill([
            'organisation_id' => $organisation->id, 'brand_id' => $brand->id,
            'title' => $data['title'], 'source_type' => $data['source_type'],
            'source_url' => $url !== '' ? $url : null,
            'object_key' => null, 'content_text' => $content !== '' ? $content : null,
            'content_hash' => $hash, 'trust_status' => TrustStatus::PENDING->value,
            'retrieved_at' => now(), 'submitted_by_user_id' => $actor->id,
        ])->save();

        return $row;
    }

    public function findSource(Organisation $organisation, Brand $brand, string $uuid): BrandKnowledgeSource
    {
        return $this->scope(BrandKnowledgeSource::class, $organisation, $brand)
            ->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
    }

    public function reviewSource(BrandKnowledgeSource $source, User $actor, string $decision): void
    {
        $source->forceFill([
            'trust_status' => $decision === 'VERIFY' ? TrustStatus::TRUSTED->value : TrustStatus::REJECTED->value,
            'verified_at' => now(), 'verified_by_user_id' => $actor->id,
        ])->save();
    }

    public function findClaim(Organisation $organisation, Brand $brand, string $uuid): BrandClaim
    {
        return $this->scope(BrandClaim::class, $organisation, $brand)
            ->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
    }

    public function createEvidence(Organisation $organisation, Brand $brand, BrandClaim $claim, BrandKnowledgeSource $source, User $actor, array $data): BrandClaimEvidence
    {
        if ($this->scope(BrandClaimEvidence::class, $organisation, $brand)
            ->where('claim_id', $claim->id)->where('knowledge_source_id', $source->id)->exists()) {
            throw ValidationException::withMessages(['source_uuid' => 'This source is already attached to the claim.']);
        }
        $row = new BrandClaimEvidence;
        $row->forceFill([
            'organisation_id' => $organisation->id, 'brand_id' => $brand->id,
            'claim_id' => $claim->id, 'knowledge_source_id' => $source->id,
            'supporting_excerpt' => $data['supporting_excerpt'],
            'source_locator' => $data['source_locator'] ?? null,
            'review_state' => EvidenceReviewState::PENDING->value,
            'submitted_by_user_id' => $actor->id,
        ])->save();

        return $row;
    }

    public function findEvidence(Organisation $organisation, Brand $brand, string $uuid): BrandClaimEvidence
    {
        return $this->scope(BrandClaimEvidence::class, $organisation, $brand)
            ->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
    }

    public function reviewEvidence(BrandClaimEvidence $evidence, User $actor, string $decision): void
    {
        $evidence->forceFill([
            'review_state' => $decision === 'VERIFY' ? EvidenceReviewState::VERIFIED->value : EvidenceReviewState::REJECTED->value,
            'reviewed_at' => now(), 'reviewed_by_user_id' => $actor->id,
        ])->save();
    }

    public function setClaimState(BrandClaim $claim, string $status, ?User $reviewer = null): void
    {
        $fields = ['status' => $status];
        if ($reviewer !== null && $status === ClaimStatus::APPROVED->value) {
            $fields['approved_by_user_id'] = $reviewer->id;
            $fields['approved_at'] = now();
        }
        $claim->forceFill($fields)->save();
    }

    public function policyForClaims(Organisation $organisation, Brand $brand): array
    {
        $row = $this->scope(BrandClaimPolicy::class, $organisation, $brand)->first();

        return $row?->only([
            'approved_claims_only', 'evidence_required_for_quantified',
            'evidence_required_for_comparisons', 'allow_guaranteed_outcome_claims',
            'require_review_for_new_claims',
        ]) ?? [
            'approved_claims_only' => true, 'evidence_required_for_quantified' => true,
            'evidence_required_for_comparisons' => true, 'allow_guaranteed_outcome_claims' => false,
            'require_review_for_new_claims' => true,
        ];
    }

    public function hasVerifiedTrustedEvidence(Organisation $organisation, Brand $brand, BrandClaim $claim): bool
    {
        return $this->scope(BrandClaimEvidence::class, $organisation, $brand)
            ->where('claim_id', $claim->id)
            ->where('review_state', EvidenceReviewState::VERIFIED->value)
            ->whereHas('knowledgeSource', function (Builder $query) use ($organisation, $brand): void {
                $query->where('organisation_id', $organisation->id)
                    ->where('brand_id', $brand->id)
                    ->where('trust_status', TrustStatus::TRUSTED->value);
            })->exists();
    }

    public function recordClaimReview(Organisation $organisation, Brand $brand, BrandClaim $claim, User $reviewer, string $decision, string $rationale): void
    {
        $row = new BrandClaimReviewEvent;
        $row->forceFill([
            'organisation_id' => $organisation->id, 'brand_id' => $brand->id, 'claim_id' => $claim->id,
            'reviewer_user_id' => $reviewer->id, 'decision' => $decision,
            'rationale' => $rationale, 'claim_hash' => hash('sha256', $claim->claim_text),
            'created_at' => now(),
        ])->save();
        if ($decision === 'APPROVE') {
            $evidenceRows = $this->scope(BrandClaimEvidence::class, $organisation, $brand)
                ->where('claim_id', $claim->id)
                ->where('review_state', EvidenceReviewState::VERIFIED->value)
                ->whereHas('knowledgeSource', fn (Builder $query) => $query
                    ->where('organisation_id', $organisation->id)
                    ->where('brand_id', $brand->id)
                    ->where('trust_status', TrustStatus::TRUSTED->value))
                ->with('knowledgeSource')->get();
            foreach ($evidenceRows as $item) {
                $snapshot = new BrandClaimReviewEvidence;
                $snapshot->forceFill([
                    'organisation_id' => $organisation->id, 'brand_id' => $brand->id,
                    'review_event_id' => $row->id, 'claim_evidence_id' => $item->id,
                    'source_content_hash' => $item->knowledgeSource->content_hash,
                    'excerpt_hash' => hash('sha256', $item->supporting_excerpt),
                    'created_at' => now(),
                ])->save();
            }
        }
    }

    public function findApprovalPolicy(Organisation $organisation, Brand $brand, string $uuid): BrandApprovalPolicy
    {
        return $this->scope(BrandApprovalPolicy::class, $organisation, $brand)
            ->with(['rules'])->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
    }

    public function activatePolicy(Organisation $organisation, Brand $brand, BrandApprovalPolicy $policy, User $actor): void
    {
        // The parent brand is locked by the service, so policy-version transitions serialize.
        $previous = $this->scope(BrandApprovalPolicy::class, $organisation, $brand)
            ->where('state', ApprovalPolicyState::ACTIVE->value)
            ->where('id', '!=', $policy->id)->lockForUpdate()->get();
        foreach ($previous as $active) {
            $active->forceFill(['state' => ApprovalPolicyState::RETIRED->value, 'retired_at' => now()])->save();
        }
        $policy->forceFill([
            'state' => ApprovalPolicyState::ACTIVE->value,
            'activated_at' => now(), 'activated_by_user_id' => $actor->id,
        ])->save();
    }
}
