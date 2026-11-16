<?php

namespace Database\Factories;

use App\Models\MetaDataDeletionReviewDecision;
use App\Models\MetaDataDeletionTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MetaDataDeletionReviewDecision> */
final class MetaDataDeletionReviewDecisionFactory extends Factory
{
    protected $model = MetaDataDeletionReviewDecision::class;

    public function definition(): array
    {
        return [
            'disposition' => 'CLEARED',
            'explanation' => 'All provider-derived personal information was verified as removed.',
            'evidence_reference' => 'privacy-audit-fixture-'.fake()->uuid(),
            'reviewed_by_user_id' => User::factory(),
        ];
    }

    public function forTarget(MetaDataDeletionTarget $target): static
    {
        return $this->state(fn () => ['organisation_id' => $target->organisation_id, 'target_id' => $target->id]);
    }
}
