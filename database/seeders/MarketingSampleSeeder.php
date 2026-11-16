<?php

namespace Database\Seeders;

use App\Enums\ApprovalActionKind;
use App\Enums\ApprovalPolicyState;
use App\Enums\AudienceKind;
use App\Enums\ClaimCategory;
use App\Enums\ClaimStatus;
use App\Enums\DecisionRole;
use App\Enums\Locale;
use App\Enums\VoiceEnforcement;
use App\Enums\VoiceRuleKind;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarketingSampleSeeder extends Seeder
{
    public function run(): void
    {
        // Fixed IDs from the v1.2 sample seed.
        $organisationId = '11111111-1111-4111-8111-111111111111';
        $brandId = '33333333-3333-4333-8333-333333333333';

        // ---- Guard: skip if already seeded --------------------------------
        $alreadySeeded = DB::table('brand_audience_segments')
            ->where('organisation_id', $organisationId)
            ->where('brand_id', $brandId)
            ->where('name', 'Nigerian private school proprietors')
            ->exists();

        if ($alreadySeeded) {
            $this->command?->info('Marketing v1.3 sample seed already present — skipping.');

            return;
        }

        DB::transaction(function () use ($organisationId, $brandId) {
            // -----------------------------------------------------------------
            // A. Audience
            // -----------------------------------------------------------------
            $audienceId = DB::table('brand_audience_segments')->insertGetId([
                'organisation_id' => $organisationId,
                'brand_id' => $brandId,
                'name' => 'Nigerian private school proprietors',
                'description' => 'Owners and operators evaluating school management and teaching software',
                'audience_kind' => AudienceKind::BUSINESS->value,
                'priority' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id');

            DB::table('brand_audience_roles')->insert([
                'organisation_id' => $organisationId,
                'brand_id' => $brandId,
                'audience_segment_id' => $audienceId,
                'role_title' => 'School proprietor',
                'decision_role' => DecisionRole::DECISION_MAKER->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('brand_audience_locations')->insert([
                'organisation_id' => $organisationId,
                'brand_id' => $brandId,
                'audience_segment_id' => $audienceId,
                'country_code' => 'NG',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('brand_audience_pain_points')->insert([
                'organisation_id' => $organisationId,
                'brand_id' => $brandId,
                'audience_segment_id' => $audienceId,
                'description' => 'Time-consuming administrative reporting',
                'priority' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('brand_audience_goals')->insert([
                'organisation_id' => $organisationId,
                'brand_id' => $brandId,
                'audience_segment_id' => $audienceId,
                'description' => 'Better operational visibility and staff productivity',
                'priority' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // -----------------------------------------------------------------
            // B. Voice
            // -----------------------------------------------------------------
            $voiceProfileId = DB::table('brand_voice_profiles')->insertGetId([
                'organisation_id' => $organisationId,
                'brand_id' => $brandId,
                'name' => 'Pensuh default',
                'locale' => Locale::EN_NG->value,
                'tone_description' => 'Clear, factual and practical',
                'formality' => 4,
                'warmth' => 3,
                'directness' => 4,
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id');

            DB::table('brand_voice_rules')->insert([
                [
                    'organisation_id' => $organisationId,
                    'brand_id' => $brandId,
                    'voice_profile_id' => $voiceProfileId,
                    'rule_kind' => VoiceRuleKind::DO->value,
                    'instruction' => 'Describe concrete school administration or teaching outcomes',
                    'enforcement' => VoiceEnforcement::MANDATORY->value,
                    'priority' => 10,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'organisation_id' => $organisationId,
                    'brand_id' => $brandId,
                    'voice_profile_id' => $voiceProfileId,
                    'rule_kind' => VoiceRuleKind::AVOID->value,
                    'instruction' => 'Avoid guarantees of academic improvement without credible support',
                    'enforcement' => VoiceEnforcement::MANDATORY->value,
                    'priority' => 20,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // -----------------------------------------------------------------
            // C. Claim policy + draft claim
            // -----------------------------------------------------------------
            DB::table('brand_claim_policies')->insert([
                'organisation_id' => $organisationId,
                'brand_id' => $brandId,
                'approved_claims_only' => true,
                'evidence_required_for_quantified' => true,
                'updated_at' => now(),
            ]);

            DB::table('brand_claims')->insert([
                'organisation_id' => $organisationId,
                'brand_id' => $brandId,
                'claim_text' => 'PENCIL assists teachers with drafting lesson plans and assessments when enabled',
                'claim_category' => ClaimCategory::PRODUCT_CAPABILITY->value,
                'status' => ClaimStatus::DRAFT->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // -----------------------------------------------------------------
            // D. Approval policy (DRAFT → insert rules → ACTIVATE)
            // -----------------------------------------------------------------
            $policyId = DB::table('brand_approval_policies')->insertGetId([
                'organisation_id' => $organisationId,
                'brand_id' => $brandId,
                'policy_version' => 1,
                'description' => 'Human approval before external publication; no self-approval',
                'state' => ApprovalPolicyState::DRAFT->value,
                'external_publishing_requires_approval' => true,
                'forbid_self_approval' => true,
                'default_required_approvals' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id');

            // Rules must be inserted while the policy is still DRAFT
            // (enforced by trg_guard_approval_rule_mutation).
            DB::table('brand_approval_rules')->insert([
                'organisation_id' => $organisationId,
                'brand_id' => $brandId,
                'policy_id' => $policyId,
                'rule_name' => 'All external publishing',
                'priority' => 10,
                'action_kind' => ApprovalActionKind::PUBLISH->value,
                'requires_human_approval' => true,
                'required_approvals' => 1,
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Activate the policy. This freezes it (guard_approval_policy_version).
            DB::table('brand_approval_policies')
                ->where('organisation_id', $organisationId)
                ->where('brand_id', $brandId)
                ->where('id', $policyId)
                ->update([
                    'state' => ApprovalPolicyState::ACTIVE->value,
                    'activated_at' => now(),
                    'updated_at' => now(),
                ]);
        });
    }
}
