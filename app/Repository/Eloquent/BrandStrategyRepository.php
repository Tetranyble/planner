<?php

namespace App\Repository\Eloquent;

use App\Enums\ApprovalPolicyState;
use App\Enums\ClaimStatus;
use App\Enums\StatusEnum;
use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\BrandApprovalRule;
use App\Models\BrandAudienceGoal;
use App\Models\BrandAudiencePainPoint;
use App\Models\BrandAudienceRole;
use App\Models\BrandAudienceSegment;
use App\Models\BrandClaim;
use App\Models\BrandClaimEvidence;
use App\Models\BrandClaimPolicy;
use App\Models\BrandKnowledgeSource;
use App\Models\BrandProfile;
use App\Models\BrandVoiceExample;
use App\Models\BrandVoiceProfile;
use App\Models\BrandVoiceRule;
use App\Models\BrandVoiceTerm;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\BrandStrategyRepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/** All persistence stays inside this tenant-bounded repository. Never expose unscoped find(). */
final class BrandStrategyRepository implements BrandStrategyRepositoryContract
{
    private function scope(string $model, Organisation $organisation, Brand $brand): Builder
    {
        return $model::query()->where('organisation_id', $organisation->getKey())
            ->where('brand_id', $brand->getKey());
    }

    private function insert(string $model, Organisation $organisation, Brand $brand, array $data): object
    {
        $record = new $model;
        $record->forceFill([
            'organisation_id' => $organisation->getKey(), 'brand_id' => $brand->getKey(), ...$data,
        ])->save();

        return $record;
    }

    public function snapshot(Organisation $organisation, Brand $brand): array
    {
        $profile = $this->scope(BrandProfile::class, $organisation, $brand)->first();
        $claimPolicy = $this->scope(BrandClaimPolicy::class, $organisation, $brand)->first();
        $audiences = $this->scope(BrandAudienceSegment::class, $organisation, $brand)
            ->with(['roles', 'painPoints', 'goals'])->orderBy('priority')->orderBy('id')->get();
        $voices = $this->scope(BrandVoiceProfile::class, $organisation, $brand)
            ->with(['rules', 'terms', 'examples'])->orderByDesc('is_default')->orderBy('id')->get();
        $claims = $this->scope(BrandClaim::class, $organisation, $brand)
            ->latest('id')->limit(100)->get();
        $sources = $this->scope(BrandKnowledgeSource::class, $organisation, $brand)->orderByDesc('id')->limit(100)->get();
        $evidence = $this->scope(BrandClaimEvidence::class, $organisation, $brand)->orderByDesc('id')->limit(200)->get();
        $policies = $this->scope(BrandApprovalPolicy::class, $organisation, $brand)
            ->with('rules')->orderByDesc('policy_version')->limit(25)->get();

        return [
            'profile' => $profile?->only(['uuid', 'positioning', 'default_locale', 'updated_at']),
            'audiences' => $audiences->map(function ($row) {
                return [
                    ...$row->only(['uuid', 'name', 'description', 'audience_kind', 'priority', 'status']),
                    'roles' => $row->roles->map->only(['role', 'decision_role'])->all(),
                    'pain_points' => $row->painPoints->sortBy('priority')->map->only(['description', 'priority'])->values()->all(),
                    'goals' => $row->goals->sortBy('priority')->map->only(['description', 'priority'])->values()->all(),
                ];
            })->all(),
            'voices' => $voices->map(function ($row) {
                return [
                    ...$row->only(['uuid', 'name', 'locale', 'tone_description', 'formality', 'warmth', 'directness', 'is_default', 'active']),
                    'rules' => $row->rules->sortBy('priority')->map->only(['rule_kind', 'instruction', 'enforcement', 'platform', 'priority'])->values()->all(),
                    'terms' => $row->terms->map->only(['term', 'usage', 'replacement_term', 'rationale'])->all(),
                    'examples' => $row->examples->map->only(['example_kind', 'example_text', 'explanation'])->all(),
                ];
            })->all(),
            'claim_policy' => $claimPolicy?->only([
                'approved_claims_only', 'evidence_required_for_quantified', 'evidence_required_for_comparisons',
                'allow_guaranteed_outcome_claims', 'require_review_for_new_claims',
            ]) ?? [
                'approved_claims_only' => true, 'evidence_required_for_quantified' => true,
                'evidence_required_for_comparisons' => true, 'allow_guaranteed_outcome_claims' => false,
                'require_review_for_new_claims' => true,
            ],
            'claims' => $claims->map->only(['uuid', 'claim_text', 'claim_category', 'status', 'valid_from', 'valid_until'])->all(),
            'knowledge_sources' => $sources->map->only(['uuid', 'title', 'source_type', 'source_url', 'trust_status', 'retrieved_at', 'verified_at'])->all(),
            'claim_evidence' => $evidence->map(function ($row) use ($claims, $sources) {
                return [
                    ...$row->only(['uuid', 'supporting_excerpt', 'source_locator', 'review_state']),
                    'claim_uuid' => $claims->firstWhere('id', $row->claim_id)?->uuid,
                    'source_uuid' => $sources->firstWhere('id', $row->knowledge_source_id)?->uuid,
                ];
            })->all(),
            'approval_policies' => $policies->map(function ($row) {
                return [
                    ...$row->only(['uuid', 'policy_version', 'description', 'state', 'external_publishing_requires_approval',
                        'forbid_self_approval', 'default_required_approvals', 'activated_at', 'retired_at']),
                    'rules' => $row->rules->sortBy('priority')->map->only(['rule_name', 'priority', 'action_kind',
                        'platform', 'content_kind', 'ai_generated', 'includes_sensitive_claim',
                        'requires_human_approval', 'required_approvals', 'forbid_self_approval', 'is_enabled'])->values()->all(),
                ];
            })->all(),
        ];
    }

    public function saveProfile(Organisation $organisation, Brand $brand, User $actor, array $data): void
    {
        $row = $this->scope(BrandProfile::class, $organisation, $brand)->first()
            ?? new BrandProfile;
        $row->forceFill([
            'organisation_id' => $organisation->id, 'brand_id' => $brand->id,
            'updated_by_user_id' => $actor->id, 'positioning' => $data['positioning'],
            'default_locale' => $data['default_locale'],
        ])->save();
    }

    public function createAudience(Organisation $organisation, Brand $brand, array $data): string
    {
        $row = $this->insert(BrandAudienceSegment::class, $organisation, $brand, [
            'name' => $data['name'], 'description' => $data['description'] ?? null,
            'audience_kind' => $data['audience_kind'], 'priority' => $data['priority'] ?? 50,
            'status' => StatusEnum::ACTIVE->value,
        ]);
        foreach ($data['roles'] ?? [] as $role) {
            $this->insert(BrandAudienceRole::class, $organisation, $brand, [
                'audience_segment_id' => $row->id, 'role' => $role['role'],
                'decision_role' => $role['decision_role'],
            ]);
        }
        foreach (['pain_points' => BrandAudiencePainPoint::class, 'goals' => BrandAudienceGoal::class] as $field => $model) {
            foreach ($data[$field] ?? [] as $item) {
                $this->insert($model, $organisation, $brand, [
                    'audience_segment_id' => $row->id, 'description' => $item['description'],
                    'priority' => $item['priority'] ?? 50,
                ]);
            }
        }

        return (string) $row->uuid;
    }

    public function createVoice(Organisation $organisation, Brand $brand, array $data): string
    {
        // Caller holds the parent brand row lock, serialising concurrent default changes.
        if ($data['is_default'] ?? false) {
            $this->scope(BrandVoiceProfile::class, $organisation, $brand)
                ->where('locale', $data['locale'])->where('is_default', true)
                ->update(['is_default' => false]);
        }
        $row = $this->insert(BrandVoiceProfile::class, $organisation, $brand, [
            'name' => $data['name'], 'locale' => $data['locale'],
            'tone_description' => $data['tone_description'] ?? null,
            'formality' => $data['formality'] ?? null, 'warmth' => $data['warmth'] ?? null,
            'directness' => $data['directness'] ?? null,
            'is_default' => $data['is_default'] ?? false, 'active' => true,
        ]);
        foreach ($data['rules'] ?? [] as $rule) {
            $this->insert(BrandVoiceRule::class, $organisation, $brand, [
                'voice_profile_id' => $row->id, 'rule_kind' => $rule['rule_kind'],
                'instruction' => $rule['instruction'], 'enforcement' => $rule['enforcement'] ?? 'GUIDELINE',
                'priority' => $rule['priority'] ?? 50,
            ]);
        }
        foreach ($data['terms'] ?? [] as $term) {
            $this->insert(BrandVoiceTerm::class, $organisation, $brand, [
                'voice_profile_id' => $row->id, 'term' => $term['term'], 'usage' => $term['usage'],
                'replacement_term' => $term['replacement_term'] ?? null,
                'rationale' => $term['rationale'] ?? null,
            ]);
        }
        foreach ($data['examples'] ?? [] as $example) {
            $this->insert(BrandVoiceExample::class, $organisation, $brand, [
                'voice_profile_id' => $row->id, 'example_kind' => $example['example_kind'],
                'example_text' => $example['example_text'], 'explanation' => $example['explanation'] ?? null,
            ]);
        }

        return (string) $row->uuid;
    }

    public function saveClaimPolicy(Organisation $organisation, Brand $brand, array $data): void
    {
        // Unique (organisation_id, brand_id) is enforced by the accompanying additive migration.
        $row = $this->scope(BrandClaimPolicy::class, $organisation, $brand)->first() ?? new BrandClaimPolicy;
        $row->forceFill(['organisation_id' => $organisation->id, 'brand_id' => $brand->id, ...$data])->save();
    }

    public function createDraftClaim(Organisation $organisation, Brand $brand, User $actor, array $data): string
    {
        $row = $this->insert(BrandClaim::class, $organisation, $brand, [
            'claim_text' => $data['claim_text'], 'claim_category' => $data['claim_category'],
            'created_by_user_id' => $actor->id,
            'valid_from' => $data['valid_from'] ?? null, 'valid_until' => $data['valid_until'] ?? null,
            'status' => ClaimStatus::DRAFT->value,
        ]);

        return (string) $row->uuid;
    }

    public function createDraftApprovalPolicy(Organisation $organisation, Brand $brand, User $actor, array $data): string
    {
        // Parent brand is locked by service to avoid assigning duplicate policy versions.
        $version = ((int) $this->scope(BrandApprovalPolicy::class, $organisation, $brand)
            ->max('policy_version')) + 1;
        $row = $this->insert(BrandApprovalPolicy::class, $organisation, $brand, [
            'policy_version' => $version, 'description' => $data['description'] ?? null,
            'created_by_user_id' => $actor->id,
            'state' => ApprovalPolicyState::DRAFT->value,
            'external_publishing_requires_approval' => true,
            'forbid_self_approval' => true,
            'default_required_approvals' => $data['default_required_approvals'] ?? 1,
        ]);
        foreach ($data['rules'] ?? [] as $rule) {
            $this->insert(BrandApprovalRule::class, $organisation, $brand, [
                'policy_id' => $row->id, 'rule_name' => $rule['rule_name'],
                'priority' => $rule['priority'], 'action_kind' => $rule['action_kind'],
                'platform' => $rule['platform'] ?? null, 'content_kind' => $rule['content_kind'] ?? null,
                'requires_human_approval' => true,
                'required_approvals' => $rule['required_approvals'] ?? null,
                'forbid_self_approval' => true, 'is_enabled' => true,
            ]);
        }

        return (string) $row->uuid;
    }
}
