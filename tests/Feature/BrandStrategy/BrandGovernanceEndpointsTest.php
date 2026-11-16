<?php

namespace Tests\Feature\BrandStrategy;

use App\Authorization\CurrentOrganisation;
use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\BrandClaim;
use App\Models\BrandClaimEvidence;
use App\Models\BrandClaimReviewEvent;
use App\Models\BrandClaimReviewEvidence;
use App\Models\BrandKnowledgeSource;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Membership\OrganisationMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tetranyble\Kinship\Models\Role;

/** MySQL + real Kinship route middleware; uses model factories, never DB facade seeding. */
final class BrandGovernanceEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function provision(User $owner, string $name): array
    {
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => $name])->assertCreated();
        $organisation = Organisation::query()->where('name', $name)->firstOrFail();
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$organisation->uuid}/brands", ['name' => $name.' Brand'])->assertCreated();
        $brand = Brand::query()->where('organisation_id', $organisation->id)->firstOrFail();

        return [$organisation, $brand, "/api/v1/organisations/{$organisation->uuid}/brands/{$brand->uuid}/strategy"];
    }

    private function addContributor(User $owner, Organisation $organisation, Brand $brand): User
    {
        $contributor = User::factory()->create();
        $role = Role::query()->forWorkspace($organisation->id)->where('name', 'contributor')->firstOrFail();
        $this->actingAs($owner);
        app(CurrentOrganisation::class)->within($owner, (int) $organisation->id, function () use ($organisation, $contributor, $role): void {
            app(OrganisationMembershipService::class)->grant($organisation, $contributor, $role);
        });
        $url = "/api/v1/organisations/{$organisation->uuid}/brands/{$brand->uuid}/members";
        $this->actingAs($owner)->postJson($url, ['user_id' => $contributor->id, 'role' => 'contributor'])->assertCreated();

        return $contributor;
    }

    public function test_evidence_backed_quantified_claim_requires_independent_source_and_evidence_verification(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$path] = $this->provision($owner, 'Governance Test A');
        $author = $this->addContributor($owner, $org, $brand);
        $this->actingAs($author)->postJson("{$path}/knowledge-sources", [
            'title' => 'Demo source', 'source_type' => 'MANUAL',
            'content_text' => 'Sample, not verified proof of customer outcomes.',
        ])->assertCreated()->assertJsonPath('data.trust_status', 'PENDING');
        $source = BrandKnowledgeSource::query()->where('brand_id', $brand->id)->firstOrFail();
        $this->actingAs($author)->postJson("{$path}/claims", [
            'claim_text' => 'A quantified, hypothetical performance claim', 'claim_category' => 'QUANTIFIED',
        ])->assertCreated();
        $claim = BrandClaim::query()->where('brand_id', $brand->id)->firstOrFail();
        $this->actingAs($author)->postJson("{$path}/claims/{$claim->uuid}/evidence", [
            'source_uuid' => $source->uuid, 'supporting_excerpt' => 'Sample evidence excerpt requiring independent verification.',
        ])->assertCreated();
        $evidence = BrandClaimEvidence::query()->where('claim_id', $claim->id)->firstOrFail();
        $this->actingAs($author)->postJson("{$path}/claims/{$claim->uuid}/submit-review")->assertOk();
        $this->actingAs($owner)->postJson("{$path}/claims/{$claim->uuid}/review", [
            'decision' => 'APPROVE', 'rationale' => 'Claims must be backed by independently verified evidence.',
        ])->assertStatus(409);
        $this->actingAs($owner)->postJson("{$path}/knowledge-sources/{$source->uuid}/review", [
            'decision' => 'VERIFY', 'rationale' => 'Manually checked the source against the provided reference.',
        ])->assertOk();
        $this->actingAs($owner)->postJson("{$path}/evidence/{$evidence->uuid}/review", [
            'decision' => 'VERIFY', 'rationale' => 'The submitted passage matches the reviewed source.',
        ])->assertOk();
        $this->actingAs($owner)->postJson("{$path}/claims/{$claim->uuid}/review", [
            'decision' => 'APPROVE', 'rationale' => 'Reviewed and verified the supporting quantitative evidence.',
        ])->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->assertDatabaseHas('brand_claims', ['id' => $claim->id, 'status' => 'APPROVED', 'approved_by_user_id' => $owner->id]);
        $this->assertDatabaseHas('brand_claim_review_events', ['claim_id' => $claim->id, 'decision' => 'APPROVE']);
        $this->assertSame(1, BrandClaimReviewEvent::query()->where('claim_id', $claim->id)->count());
        $event = BrandClaimReviewEvent::query()->where('claim_id', $claim->id)->firstOrFail();
        $snapshot = BrandClaimReviewEvidence::query()->where('review_event_id', $event->id)->firstOrFail();
        $this->assertSame($source->content_hash, $snapshot->source_content_hash);
        $this->assertSame(hash('sha256', $evidence->supporting_excerpt), $snapshot->excerpt_hash);
    }

    public function test_authors_cannot_review_their_own_source_and_claims_cannot_be_approved_twice(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$path] = $this->provision($owner, 'Governance Test B');
        $source = BrandKnowledgeSource::factory()->forBrand($brand)->create(['submitted_by_user_id' => $owner->id]);
        $this->actingAs($owner)->postJson("{$path}/knowledge-sources/{$source->uuid}/review", [
            'decision' => 'VERIFY', 'rationale' => 'Trying to review an own source is not permitted.',
        ])->assertForbidden();
        $this->assertSame('PENDING', $source->fresh()->trust_status);
        $other = $this->addContributor($owner, $org, $brand);
        $this->actingAs($other)->postJson("{$path}/claims", [
            'claim_text' => 'Demonstration product capability', 'claim_category' => 'PRODUCT_CAPABILITY',
        ])->assertCreated();
        $claim = BrandClaim::query()->where('brand_id', $brand->id)->firstOrFail();
        $this->actingAs($other)->postJson("{$path}/claims/{$claim->uuid}/submit-review")->assertOk();
        $this->actingAs($owner)->postJson("{$path}/claims/{$claim->uuid}/review", [
            'decision' => 'REJECT', 'rationale' => 'The example claim is not supported by authoritative evidence.',
        ])->assertOk();
        $this->actingAs($owner)->postJson("{$path}/claims/{$claim->uuid}/review", [
            'decision' => 'REJECT', 'rationale' => 'Cannot review this same claim a second time.',
        ])->assertStatus(409);
        $this->assertSame(1, BrandClaimReviewEvent::query()->where('claim_id', $claim->id)->count());
        $this->assertDatabaseCount('brand_claim_review_evidence', 0);
    }

    public function test_only_one_approval_policy_becomes_active_and_previous_version_is_retired(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$path] = $this->provision($owner, 'Governance Test C');
        foreach ([1, 2] as $version) {
            $this->actingAs($owner)->postJson("{$path}/approval-policies", [
                'description' => "Policy v{$version}", 'default_required_approvals' => 2,
                'rules' => [['rule_name' => 'Review publishing', 'priority' => 10, 'action_kind' => 'PUBLISH', 'required_approvals' => 2]],
            ])->assertCreated();
        }
        $policies = BrandApprovalPolicy::query()->where('brand_id', $brand->id)->orderBy('policy_version')->get();
        $this->actingAs($owner)->postJson("{$path}/approval-policies/{$policies[0]->uuid}/activate")->assertOk();
        $this->actingAs($owner)->postJson("{$path}/approval-policies/{$policies[1]->uuid}/activate")->assertOk();
        $this->assertSame('RETIRED', $policies[0]->fresh()->state);
        $this->assertSame('ACTIVE', $policies[1]->fresh()->state);
        $this->assertSame(1, BrandApprovalPolicy::query()->where('brand_id', $brand->id)->where('state', 'ACTIVE')->count());
        $this->actingAs($owner)->postJson("{$path}/approval-policies/{$policies[0]->uuid}/activate")->assertStatus(409);
    }

    public function test_owner_cannot_approve_own_claim_or_verify_own_evidence(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$path] = $this->provision($owner, 'Governance Test Own Approval');
        $claim = BrandClaim::factory()->forBrand($brand)->create(['created_by_user_id' => $owner->id]);
        $source = BrandKnowledgeSource::factory()->forBrand($brand)->trusted()->create();
        $evidence = BrandClaimEvidence::factory()->forBrandClaim($claim)->create([
            'knowledge_source_id' => $source->id, 'submitted_by_user_id' => $owner->id,
        ]);
        $this->actingAs($owner)->postJson("{$path}/evidence/{$evidence->uuid}/review", [
            'decision' => 'VERIFY', 'rationale' => 'Attempt to verify evidence written by the reviewer.',
        ])->assertForbidden();
        $this->actingAs($owner)->postJson("{$path}/claims/{$claim->uuid}/submit-review")->assertOk();
        $this->actingAs($owner)->postJson("{$path}/claims/{$claim->uuid}/review", [
            'decision' => 'APPROVE', 'rationale' => 'Attempt to approve a claim authored by reviewer.',
        ])->assertForbidden();
        $this->assertSame('IN_REVIEW', $claim->fresh()->status);
        $this->assertDatabaseMissing('brand_claim_review_events', ['claim_id' => $claim->id]);
    }

    public function test_cross_brand_source_and_evidence_are_rejected_at_repository_boundary(): void
    {
        $one = User::factory()->create();
        [$orgA,$brandA,$pathA] = $this->provision($one, 'Governance Test D');
        $two = User::factory()->create();
        [$orgB,$brandB,$pathB] = $this->provision($two, 'Governance Test E');
        $claim = BrandClaim::factory()->forBrand($brandA)->create();
        $foreign = BrandKnowledgeSource::factory()->forBrand($brandB)->trusted()->create();
        $this->actingAs($one)->postJson("{$pathA}/claims/{$claim->uuid}/evidence", [
            'source_uuid' => $foreign->uuid, 'supporting_excerpt' => 'This evidence source belongs to another brand.',
        ])->assertNotFound();
        $this->actingAs($one)->postJson("{$pathB}/knowledge-sources", [
            'title' => 'Unauthorized external source', 'source_type' => 'MANUAL', 'content_text' => 'Not permitted.',
        ])->assertForbidden();
        $this->assertDatabaseMissing('brand_claim_evidence', ['claim_id' => $claim->id, 'knowledge_source_id' => $foreign->id]);
    }

    public function test_evidence_cannot_be_verified_before_source_trust_and_invalid_activation_is_blocked(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$path] = $this->provision($owner, 'Governance Test F');
        $author = $this->addContributor($owner, $org, $brand);
        $source = BrandKnowledgeSource::factory()->forBrand($brand)->create(['submitted_by_user_id' => $author->id]);
        $claim = BrandClaim::factory()->forBrand($brand)->create(['created_by_user_id' => $author->id]);
        $evidence = BrandClaimEvidence::factory()->forBrandClaim($claim)->create([
            'knowledge_source_id' => $source->id, 'submitted_by_user_id' => $author->id,
        ]);
        $this->actingAs($owner)->postJson("{$path}/evidence/{$evidence->uuid}/review", [
            'decision' => 'VERIFY', 'rationale' => 'Not yet eligible to verify this unsupported source.',
        ])->assertStatus(409);
        $draft = BrandApprovalPolicy::factory()->forBrand($brand)->create(['policy_version' => 1]);
        $this->actingAs($owner)->postJson("{$path}/approval-policies/{$draft->uuid}/activate")->assertStatus(409);
        $this->assertSame('DRAFT', $draft->fresh()->state);
    }
}
