<?php

namespace App\Services\BrandStrategy;

use App\Authorization\BrandPermissionGuard;
use App\Domain\BrandStrategy\ApprovalPolicyActivationRules;
use App\Domain\BrandStrategy\ClaimPublicationEligibility;
use App\Enums\ClaimStatus;
use App\Enums\EvidenceReviewState;
use App\Enums\TrustStatus;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\BrandGovernanceRepositoryContract;
use DateTimeImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Brand lock serializes governance writes; every mutation remains fully auditable. */
final class GovernBrandClaims
{
    public function __construct(
        private readonly BrandPermissionGuard $guard,
        private readonly BrandGovernanceRepositoryContract $repository,
        private readonly AuditEventRepositoryContract $audit,
        private readonly ClaimPublicationEligibility $eligibility,
        private readonly ApprovalPolicyActivationRules $activationRules,
    ) {}

    private function ensure(User $actor, Organisation $organisation, Brand $brand, string $permission): void
    {
        if ((int) $brand->organisation_id !== (int) $organisation->id || ! $this->guard->allows($actor, $brand, $permission)) {
            throw new AuthorizationException('Brand governance permission denied.');
        }
    }

    private function execute(User $actor, Organisation $organisation, Brand $brand, string $permission, callable $work): mixed
    {
        $this->ensure($actor, $organisation, $brand, $permission);

        return DB::transaction(function () use ($actor, $organisation, $brand, $permission, $work) {
            // Match membership services' lock order: organisation first, brand second.
            Organisation::query()->whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            $locked = Brand::query()->where('organisation_id', $organisation->id)
                ->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->ensure($actor, $organisation, $locked, $permission);

            return $work();
        }, attempts: 3);
    }

    private function audit(Organisation $organisation, Brand $brand, User $actor, string $event, string $uuid, array $metadata = []): void
    {
        $this->audit->record($organisation, $actor, $event, 'brand_governance', $uuid, $brand, $metadata);
    }

    public function addSource(User $actor, Organisation $organisation, Brand $brand, array $data): string
    {
        return $this->execute($actor, $organisation, $brand, 'brand_knowledge_source.store', function () use ($actor, $organisation, $brand, $data) {
            $source = $this->repository->createSource($organisation, $brand, $actor, $data);
            $this->audit($organisation, $brand, $actor, 'brand.source.submitted', $source->uuid);

            return $source->uuid;
        });
    }

    public function reviewSource(User $actor, Organisation $organisation, Brand $brand, string $uuid, string $decision, string $rationale): void
    {
        $this->execute($actor, $organisation, $brand, 'knowledge-source.verify', function () use ($actor, $organisation, $brand, $uuid, $decision, $rationale) {
            $source = $this->repository->findSource($organisation, $brand, $uuid);
            if ($source->trust_status !== TrustStatus::PENDING->value) {
                throw new ConflictHttpException('Source already reviewed.');
            }
            if ($source->submitted_by_user_id !== null && (int) $source->submitted_by_user_id === (int) $actor->id) {
                throw new AuthorizationException('A source cannot be verified by its submitter.');
            }
            $this->repository->reviewSource($source, $actor, $decision);
            $this->audit($organisation, $brand, $actor, 'brand.source.reviewed', $uuid, ['decision' => $decision, 'rationale' => $rationale]);
        });
    }

    public function addEvidence(User $actor, Organisation $organisation, Brand $brand, string $claimUuid, array $data): string
    {
        return $this->execute($actor, $organisation, $brand, 'brand_claim_evidence.store', function () use ($actor, $organisation, $brand, $claimUuid, $data) {
            $claim = $this->repository->findClaim($organisation, $brand, $claimUuid);
            if (! in_array($claim->status, [ClaimStatus::DRAFT->value, ClaimStatus::IN_REVIEW->value], true)) {
                throw new ConflictHttpException('Evidence cannot be added to a completed claim.');
            }
            $source = $this->repository->findSource($organisation, $brand, $data['source_uuid']);
            $evidence = $this->repository->createEvidence($organisation, $brand, $claim, $source, $actor, $data);
            $this->audit($organisation, $brand, $actor, 'brand.claim.evidence.submitted', $evidence->uuid, ['claim_uuid' => $claimUuid]);

            return $evidence->uuid;
        });
    }

    public function reviewEvidence(User $actor, Organisation $organisation, Brand $brand, string $uuid, string $decision, string $rationale): void
    {
        $this->execute($actor, $organisation, $brand, 'claim-evidence.verify', function () use ($actor, $organisation, $brand, $uuid, $decision, $rationale) {
            $evidence = $this->repository->findEvidence($organisation, $brand, $uuid);
            if ($evidence->review_state !== EvidenceReviewState::PENDING->value) {
                throw new ConflictHttpException('Evidence already reviewed.');
            }
            if ($evidence->submitted_by_user_id !== null && (int) $evidence->submitted_by_user_id === (int) $actor->id) {
                throw new AuthorizationException('Evidence submitters cannot verify their own submissions.');
            }
            $source = $this->repository->findSource($organisation, $brand, $evidence->knowledgeSource->uuid);
            if ($decision === 'VERIFY' && $source->trust_status !== TrustStatus::TRUSTED->value) {
                throw new ConflictHttpException('Only trusted sources can support verified evidence.');
            }
            $this->repository->reviewEvidence($evidence, $actor, $decision);
            $this->audit($organisation, $brand, $actor, 'brand.claim.evidence.reviewed', $uuid, ['decision' => $decision, 'rationale' => $rationale]);
        });
    }

    public function submitClaim(User $actor, Organisation $organisation, Brand $brand, string $uuid): void
    {
        $this->execute($actor, $organisation, $brand, 'claim.submit-review', function () use ($actor, $organisation, $brand, $uuid) {
            $claim = $this->repository->findClaim($organisation, $brand, $uuid);
            if ($claim->status !== ClaimStatus::DRAFT->value) {
                throw new ConflictHttpException('Only draft claims may be submitted.');
            }
            $this->repository->setClaimState($claim, ClaimStatus::IN_REVIEW->value);
            $this->audit($organisation, $brand, $actor, 'brand.claim.submitted', $uuid);
        });
    }

    public function reviewClaim(User $actor, Organisation $organisation, Brand $brand, string $uuid, string $decision, string $rationale): void
    {
        $this->execute($actor, $organisation, $brand, 'claim.review', function () use ($actor, $organisation, $brand, $uuid, $decision, $rationale) {
            $claim = $this->repository->findClaim($organisation, $brand, $uuid);
            if ($claim->status !== ClaimStatus::IN_REVIEW->value) {
                throw new ConflictHttpException('Only submitted claims may be reviewed.');
            }
            if ($claim->created_by_user_id !== null && (int) $claim->created_by_user_id === (int) $actor->id) {
                throw new AuthorizationException('Claim authors cannot approve or reject their own claims.');
            }
            if ($decision === 'APPROVE') {
                $verified = $this->repository->hasVerifiedTrustedEvidence($organisation, $brand, $claim);
                if (! $verified) {
                    throw new ConflictHttpException('Every approved marketing claim requires independently verified evidence from a trusted source.');
                }
                $policy = $this->repository->policyForClaims($organisation, $brand);
                $allowed = $this->eligibility->eligible([
                    'status' => ClaimStatus::APPROVED->value, 'claim_category' => $claim->claim_category,
                    'valid_from' => $claim->valid_from, 'valid_until' => $claim->valid_until,
                ], $policy, $verified, new DateTimeImmutable);
                if (! $allowed) {
                    throw new ConflictHttpException('Claim is unsupported, expired, or prohibited by claims policy.');
                }
            }
            $this->repository->setClaimState($claim, $decision === 'APPROVE' ? ClaimStatus::APPROVED->value : ClaimStatus::REJECTED->value,
                $decision === 'APPROVE' ? $actor : null);
            $this->repository->recordClaimReview($organisation, $brand, $claim, $actor, $decision, $rationale);
            $this->audit($organisation, $brand, $actor, 'brand.claim.reviewed', $uuid, ['decision' => $decision]);
        });
    }

    public function activateApprovalPolicy(User $actor, Organisation $organisation, Brand $brand, string $uuid): void
    {
        $this->execute($actor, $organisation, $brand, 'approval-policy.activate', function () use ($actor,$organisation,$brand,$uuid) {
            $policy = $this->repository->findApprovalPolicy($organisation,$brand,$uuid);
            $violations = $this->activationRules->violations($policy->getAttributes(),$policy->rules->map->getAttributes()->all());
            if ($violations !== []) {
                throw new ConflictHttpException(implode(' ', $violations));
            }
            $this->repository->activatePolicy($organisation,$brand,$policy,$actor);
            $this->audit($organisation,$brand,$actor,'brand.approval_policy.activated',$uuid,['version' => $policy->policy_version]);
        });
    }
}
