<?php

namespace Database\Factories;

use App\Models\ProviderGrantReconciliationDecision;
use App\Models\ProviderGrantReconciliationEvidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProviderGrantReconciliationDecision> */
final class ProviderGrantReconciliationDecisionFactory extends Factory
{
    protected $model = ProviderGrantReconciliationDecision::class;

    public function definition(): array
    {
        return [
            'organisation_id' => null,
            'revocation_request_id' => null,
            'evidence_id' => null,
            'reviewed_by_user_id' => User::factory(),
            'outcome' => 'ESCALATED',
            'rationale' => 'Synthetic escalation: independent review requires additional external verification.',
            'evidence_digest' => hash('sha256', fake()->uuid()),
        ];
    }

    public function forEvidence(ProviderGrantReconciliationEvidence $evidence, User $reviewer): static
    {
        return $this->state(fn () => [
            'organisation_id' => $evidence->organisation_id,
            'revocation_request_id' => $evidence->revocation_request_id,
            'evidence_id' => $evidence->id,
            'reviewed_by_user_id' => $reviewer->id,
            'evidence_digest' => $evidence->evidence_digest,
        ]);
    }
}
