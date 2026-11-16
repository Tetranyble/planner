<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\BrandApprovalRule;
use App\Models\BrandAudienceGoal;
use App\Models\BrandAudiencePainPoint;
use App\Models\BrandAudienceRole;
use App\Models\BrandAudienceSegment;
use App\Models\BrandClaim;
use App\Models\BrandClaimPolicy;
use App\Models\BrandProfile;
use App\Models\BrandVoiceProfile;
use App\Models\BrandVoiceRule;
use App\Models\BrandVoiceTerm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Opt-in: PLANNER_DEMO_BRAND_UUID=<uuid> php artisan db:seed --class=BrandStrategyDemoSeeder.
 * Demonstration-only content. Never claims a school uses the product or approves a claim.
 */
final class BrandStrategyDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demonstration marketing data may only be seeded in local/testing.');
        }
        $uuid = (string) env('PLANNER_DEMO_BRAND_UUID', '');
        if ($uuid === '') {
            throw new LogicException('Specify PLANNER_DEMO_BRAND_UUID to select an existing brand.');
        }
        $brand = Brand::query()->where('uuid', $uuid)->firstOrFail();
        $scope = ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id];
        DB::transaction(function () use ($brand, $scope): void {
            Brand::query()->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            BrandProfile::query()->firstOrCreate($scope, [
                'positioning' => 'Demonstration brand: explain practical product workflows clearly.',
                'default_locale' => 'en-NG',
            ]);
            $segment = BrandAudienceSegment::query()->firstOrCreate(
                [...$scope, 'name' => 'Example school administrators'],
                ['description' => 'Demo audience, not a verified customer list', 'audience_kind' => 'BUSINESS', 'priority' => 50, 'status' => 'ACTIVE']);
            BrandAudienceRole::query()->firstOrCreate(
                [...$scope, 'audience_segment_id' => $segment->id, 'role' => 'School proprietor', 'decision_role' => 'DECISION_MAKER']);
            BrandAudiencePainPoint::query()->firstOrCreate(
                [...$scope, 'audience_segment_id' => $segment->id, 'description' => 'Manual record-keeping'], ['priority' => 50]);
            BrandAudienceGoal::query()->firstOrCreate(
                [...$scope, 'audience_segment_id' => $segment->id, 'description' => 'Improve school operations'], ['priority' => 50]);
            $voice = BrandVoiceProfile::query()->firstOrCreate(
                [...$scope, 'name' => 'Demo professional voice', 'locale' => 'en-NG'],
                ['tone_description' => 'Calm, precise, and helpful', 'formality' => 4, 'warmth' => 4, 'directness' => 4, 'is_default' => false, 'active' => true]);
            BrandVoiceRule::query()->firstOrCreate(
                [...$scope, 'voice_profile_id' => $voice->id, 'rule_kind' => 'DO', 'instruction' => 'Only describe verified capabilities'],
                ['enforcement' => 'MANDATORY', 'priority' => 10, 'is_active' => true]);
            BrandVoiceTerm::query()->firstOrCreate(
                [...$scope, 'voice_profile_id' => $voice->id, 'term' => 'guaranteed', 'usage' => 'AVOID'],
                ['rationale' => 'Avoid unverified outcome promises']);
            BrandClaimPolicy::query()->firstOrCreate($scope, [
                'approved_claims_only' => true, 'evidence_required_for_quantified' => true,
                'evidence_required_for_comparisons' => true, 'allow_guaranteed_outcome_claims' => false,
                'require_review_for_new_claims' => true,
            ]);
            BrandClaim::query()->firstOrCreate(
                [...$scope, 'claim_text' => 'DEMONSTRATION DRAFT — verify features before use', 'claim_category' => 'PRODUCT_CAPABILITY'],
                ['status' => 'DRAFT']);
            $policy = BrandApprovalPolicy::query()->firstOrCreate(
                [...$scope, 'policy_version' => 1],
                ['description' => 'Demonstration policy: review before publishing', 'state' => 'DRAFT',
                    'external_publishing_requires_approval' => true, 'forbid_self_approval' => true,
                    'default_required_approvals' => 1]);
            BrandApprovalRule::query()->firstOrCreate(
                [...$scope, 'policy_id' => $policy->id, 'priority' => 10],
                ['rule_name' => 'Human review for publish', 'action_kind' => 'PUBLISH',
                    'requires_human_approval' => true, 'required_approvals' => 1,
                    'forbid_self_approval' => true, 'is_enabled' => true]);
        }, attempts: 3);
    }
}
