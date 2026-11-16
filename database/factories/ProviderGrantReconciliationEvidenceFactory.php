<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\ProviderGrantReconciliationEvidence;
use App\Models\ProviderGrantRevocationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProviderGrantReconciliationEvidence> */
final class ProviderGrantReconciliationEvidenceFactory extends Factory
{
    protected $model = ProviderGrantReconciliationEvidence::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'revocation_request_id' => null, // The actual scoped request is mandatory in tests.
            'submitted_by_user_id' => User::factory(),
            'source_type' => 'PROVIDER_SUPPORT',
            'reference' => 'TEST-'.str()->upper(str()->random(12)),
            'summary' => 'Synthetic provider support case confirms the current authorization status.',
            'evidence_digest' => hash('sha256', fake()->uuid()),
        ];
    }

    public function forRequest(ProviderGrantRevocationRequest $request, User $submitter): static
    {
        return $this->state(fn () => [
            'organisation_id' => $request->organisation_id,
            'revocation_request_id' => $request->id,
            'submitted_by_user_id' => $submitter->id,
        ]);
    }
}
