<?php

namespace Tests\Feature\BrandStrategy;

use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\BrandClaim;
use App\Models\BrandClaimPolicy;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Endpoints are exercised through real Kinship middleware, real factories and real MySQL. */
final class BrandStrategyEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function provision(User $owner, string $name): array
    {
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => $name])->assertCreated();
        $organisation = Organisation::query()->where('name', $name)->firstOrFail();
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$organisation->uuid}/brands", ['name' => $name.' Brand'])->assertCreated();
        $brand = Brand::query()->where('organisation_id', $organisation->id)->firstOrFail();

        return [$organisation, $brand,
            "/api/v1/organisations/{$organisation->uuid}/brands/{$brand->uuid}/strategy"];
    }

    public function test_owner_can_define_normalized_strategy_without_approving_any_claim_or_policy(): void
    {
        $owner = User::factory()->create();
        [$organisation,$brand,$url] = $this->provision($owner, 'Strategy Demo Org');
        $this->actingAs($owner)->patchJson("{$url}/profile", [
            'positioning' => 'A practical school operations and teaching platform', 'default_locale' => 'en-NG',
        ])->assertOk();
        $this->actingAs($owner)->postJson("{$url}/audiences", [
            'name' => 'Private school proprietors', 'audience_kind' => 'BUSINESS', 'priority' => 10,
            'roles' => [['role' => 'Proprietor', 'decision_role' => 'DECISION_MAKER']],
            'pain_points' => [['description' => 'Manual approvals and disconnected reports']],
            'goals' => [['description' => 'Better institutional visibility']],
        ])->assertCreated();
        $this->actingAs($owner)->postJson("{$url}/voices", [
            'name' => 'Professional', 'locale' => 'en-NG', 'is_default' => true,
            'tone_description' => 'Friendly and precise',
            'rules' => [['rule_kind' => 'DO', 'instruction' => 'Only make verifiable claims']],
            'terms' => [['term' => 'guaranteed', 'usage' => 'AVOID']],
        ])->assertCreated();
        $this->actingAs($owner)->patchJson("{$url}/claims/policy", [
            'approved_claims_only' => true, 'evidence_required_for_quantified' => true,
            'evidence_required_for_comparisons' => true, 'allow_guaranteed_outcome_claims' => false,
            'require_review_for_new_claims' => true,
        ])->assertOk();
        $this->actingAs($owner)->postJson("{$url}/claims", [
            'claim_text' => 'Draft product capability that needs evidence',
            'claim_category' => 'PRODUCT_CAPABILITY', 'status' => 'APPROVED', // ignored/never accepted
        ])->assertCreated()->assertJsonPath('data.status', 'DRAFT');
        $this->actingAs($owner)->postJson("{$url}/approval-policies", [
            'default_required_approvals' => 2, 'external_publishing_requires_approval' => false,
            'rules' => [['rule_name' => 'Review publishing', 'priority' => 10, 'action_kind' => 'PUBLISH',
                'requires_human_approval' => false]],
        ])->assertCreated()->assertJsonPath('data.state', 'DRAFT');
        $this->assertDatabaseHas('brand_profiles', ['organisation_id' => $organisation->id, 'brand_id' => $brand->id]);
        $this->assertDatabaseHas('brand_audience_roles', ['organisation_id' => $organisation->id, 'brand_id' => $brand->id, 'role' => 'Proprietor']);
        $this->assertDatabaseHas('brand_voice_rules', ['organisation_id' => $organisation->id, 'brand_id' => $brand->id]);
        $this->assertDatabaseHas('brand_claim_policies', ['organisation_id' => $organisation->id, 'brand_id' => $brand->id]);
        $this->assertSame('DRAFT', BrandClaim::query()->where('brand_id', $brand->id)->firstOrFail()->status);
        $this->assertSame('DRAFT', BrandApprovalPolicy::query()->where('brand_id', $brand->id)->firstOrFail()->state);
        $this->actingAs($owner)->getJson($url)->assertOk()
            ->assertJsonPath('data.audiences.0.roles.0.role', 'Proprietor')
            ->assertJsonPath('data.voices.0.rules.0.rule_kind', 'DO');
    }

    public function test_cross_organisation_routes_cannot_read_or_write_brand_strategy(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        [$orgA,$brandA] = $this->provision($first, 'Brand Strategy A');
        [$orgB,$brandB,$urlB] = $this->provision($second, 'Brand Strategy B');
        $this->actingAs($first)->getJson($urlB)->assertForbidden();
        $this->actingAs($first)->patchJson("{$urlB}/profile", [
            'positioning' => 'Unauthorised update', 'default_locale' => 'en-NG',
        ])->assertForbidden();
        $this->actingAs($first)->getJson("/api/v1/organisations/{$orgA->uuid}/brands/{$brandB->uuid}/strategy")
            ->assertNotFound();
        $this->assertDatabaseMissing('brand_profiles', ['brand_id' => $brandB->id]);
    }

    public function test_invalid_nested_payloads_are_rejected_before_any_child_rows_persist(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$url] = $this->provision($owner, 'Strategy Validation A');
        $this->actingAs($owner)->postJson("{$url}/audiences", [
            'name' => 'Owners', 'audience_kind' => 'BUSINESS',
            'roles' => [['role' => 'Owner', 'decision_role' => 'DECISION_MAKER'], ['role' => 'owner', 'decision_role' => 'DECISION_MAKER']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['roles.1.role']);
        $this->actingAs($owner)->postJson("{$url}/voices", [
            'name' => 'Marketing', 'locale' => 'en-NG', 'is_default' => true,
            'terms' => [['term' => 'miracle', 'usage' => 'REPLACE']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['terms.0.replacement_term']);
        $this->actingAs($owner)->postJson("{$url}/approval-policies", [
            'default_required_approvals' => 0,
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('brand_audience_segments', ['brand_id' => $brand->id]);
        $this->assertDatabaseMissing('brand_voice_profiles', ['brand_id' => $brand->id]);
        $this->assertDatabaseMissing('brand_approval_policies', ['brand_id' => $brand->id]);
    }

    public function test_claim_policy_is_singleton_and_multiple_policy_drafts_have_sequential_versions(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$url] = $this->provision($owner, 'Strategy Versioning');
        $payload = ['approved_claims_only' => true, 'evidence_required_for_quantified' => true,
            'evidence_required_for_comparisons' => true, 'allow_guaranteed_outcome_claims' => false,
            'require_review_for_new_claims' => true];
        $this->actingAs($owner)->patchJson("{$url}/claims/policy", $payload)->assertOk();
        $this->actingAs($owner)->patchJson("{$url}/claims/policy", $payload)->assertOk();
        $this->assertSame(1, BrandClaimPolicy::query()->where('brand_id', $brand->id)->count());
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($owner)->postJson("{$url}/approval-policies", [
                'default_required_approvals' => 1, 'rules' => [],
            ])->assertCreated();
        }
        $this->assertSame([1, 2], BrandApprovalPolicy::query()->where('brand_id',$brand->id)
            ->orderBy('policy_version')->pluck('policy_version')->all());
    }
}
