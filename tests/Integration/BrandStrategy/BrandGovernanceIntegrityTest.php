<?php

namespace Tests\Integration\BrandStrategy;

use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\BrandApprovalRule;
use App\Models\BrandClaim;
use App\Models\BrandClaimEvidence;
use App\Models\BrandClaimReviewEvent;
use App\Models\BrandKnowledgeSource;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MySQL real-FK and generated-column tests. Model factories only, no raw DB facade fixtures. */
final class BrandGovernanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_evidence_cannot_join_claim_and_knowledge_from_different_brands(): void
    {
        $brandA = Brand::factory()->create();
        $brandB = Brand::factory()->create();
        $claim = BrandClaim::factory()->forBrand($brandA)->create();
        $source = BrandKnowledgeSource::factory()->forBrand($brandB)->create();
        $this->expectException(QueryException::class);
        BrandClaimEvidence::factory()->forBrandClaim($claim)->create(['knowledge_source_id' => $source->id]);
    }

    public function test_claim_review_event_cannot_join_another_brand_claim(): void
    {
        $brandA = Brand::factory()->create();
        $brandB = Brand::factory()->create();
        $claim = BrandClaim::factory()->forBrand($brandB)->create();
        $reviewer = User::factory()->create();
        $this->expectException(QueryException::class);
        BrandClaimReviewEvent::factory()->forClaim($claim)->create([
            'organisation_id' => $brandA->organisation_id, 'brand_id' => $brandA->id,
            'reviewer_user_id' => $reviewer->id,
        ]);
    }

    public function test_generated_active_marker_prevents_two_active_policies_for_same_brand(): void
    {
        $brand = Brand::factory()->create();
        BrandApprovalPolicy::factory()->forBrand($brand)->create(['policy_version' => 1, 'state' => 'ACTIVE']);
        $this->expectException(QueryException::class);
        BrandApprovalPolicy::factory()->forBrand($brand)->create(['policy_version' => 2, 'state' => 'ACTIVE']);
    }

    public function test_multiple_draft_policies_are_allowed_and_governance_factories_create_valid_graph(): void
    {
        $brand = Brand::factory()->create();
        $policyA = BrandApprovalPolicy::factory()->forBrand($brand)->create(['policy_version' => 1]);
        $policyB = BrandApprovalPolicy::factory()->forBrand($brand)->create(['policy_version' => 2]);
        $rule = BrandApprovalRule::factory()->forBrandApprovalPolicy($policyA)->create();
        $claim = BrandClaim::factory()->forBrand($brand)->create();
        $source = BrandKnowledgeSource::factory()->forBrand($brand)->create();
        $evidence = BrandClaimEvidence::factory()->forBrandClaim($claim)->create(['knowledge_source_id' => $source->id]);
        $review = BrandClaimReviewEvent::factory()->forClaim($claim)->create();
        self::assertSame((int) $brand->id, (int) $rule->brand_id);
        self::assertSame((int) $brand->id, (int) $evidence->brand_id);
        self::assertSame((int) $claim->id, (int) $review->claim_id);
        self::assertSame('DRAFT', $policyB->state);
    }
}
