<?php

namespace App\Http\Requests\BrandStrategy;

final class UpdateClaimPolicyRequest extends StrategyRequest
{
    public function rules(): array
    {
        return [
            'approved_claims_only' => ['required', 'boolean'],
            'evidence_required_for_quantified' => ['required', 'boolean'],
            'evidence_required_for_comparisons' => ['required', 'boolean'],
            'allow_guaranteed_outcome_claims' => ['required', 'boolean'],
            'require_review_for_new_claims' => ['required', 'boolean'],
        ];
    }
}
