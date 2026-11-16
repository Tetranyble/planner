<?php

namespace Database\Factories;

use App\Models\MetaDataDeletionReviewDecision;
use App\Models\PrivacyRetentionFollowup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PrivacyRetentionFollowup> */
final class PrivacyRetentionFollowupFactory extends Factory
{
    protected $model = PrivacyRetentionFollowup::class;

    public function definition(): array
    {
        return [
            'due_at' => now()->subDay(), 'status' => 'DUE',
            'acknowledged_by_user_id' => null, 'acknowledged_at' => null,
        ];
    }

    public function forDecision(MetaDataDeletionReviewDecision $decision): static
    {
        return $this->state(fn () => [
            'organisation_id' => $decision->organisation_id,
            'review_decision_id' => $decision->id,
        ]);
    }
}
