<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\BrandClaim;
use App\Models\BrandClaimEvidence;
use App\Models\BrandKnowledgeSource;
use App\Models\Organisation;
use App\Models\User;

/** Tenant-enforced persistence contract. No generic unscoped find/update operations. */
interface BrandGovernanceRepositoryContract
{
    public function createSource(Organisation $organisation, Brand $brand, User $actor, array $data): BrandKnowledgeSource;

    public function findSource(Organisation $organisation, Brand $brand, string $uuid): BrandKnowledgeSource;

    public function reviewSource(BrandKnowledgeSource $source, User $actor, string $decision): void;

    public function createEvidence(Organisation $organisation, Brand $brand, BrandClaim $claim, BrandKnowledgeSource $source, User $actor, array $data): BrandClaimEvidence;

    public function findEvidence(Organisation $organisation, Brand $brand, string $uuid): BrandClaimEvidence;

    public function reviewEvidence(BrandClaimEvidence $evidence, User $actor, string $decision): void;

    public function findClaim(Organisation $organisation, Brand $brand, string $uuid): BrandClaim;

    public function setClaimState(BrandClaim $claim, string $status, ?User $reviewer = null): void;

    public function policyForClaims(Organisation $organisation, Brand $brand): array;

    public function hasVerifiedTrustedEvidence(Organisation $organisation, Brand $brand, BrandClaim $claim): bool;

    public function recordClaimReview(Organisation $organisation, Brand $brand, BrandClaim $claim, User $reviewer, string $decision, string $rationale): void;

    public function findApprovalPolicy(Organisation $organisation, Brand $brand, string $uuid): BrandApprovalPolicy;

    public function activatePolicy(Organisation $organisation, Brand $brand, BrandApprovalPolicy $policy, User $actor): void;
}
