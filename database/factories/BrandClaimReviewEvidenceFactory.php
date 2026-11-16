<?php

namespace Database\Factories;

use App\Models\BrandClaim;
use App\Models\BrandClaimEvidence;
use App\Models\BrandClaimReviewEvent;
use App\Models\BrandClaimReviewEvidence;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandClaimReviewEvidence> */
final class BrandClaimReviewEvidenceFactory extends Factory
{
    protected $model = BrandClaimReviewEvidence::class;

    public function definition(): array
    {
        return [
            'review_event_id' => BrandClaimReviewEvent::factory(),
            'organisation_id' => fn (array $a) => BrandClaimReviewEvent::query()->findOrFail($a['review_event_id'])->organisation_id,
            'brand_id' => fn (array $a) => BrandClaimReviewEvent::query()->findOrFail($a['review_event_id'])->brand_id,
            'claim_evidence_id' => fn (array $a) => BrandClaimEvidence::factory()->forBrandClaim(
                BrandClaim::query()->findOrFail(BrandClaimReviewEvent::query()->findOrFail($a['review_event_id'])->claim_id)
            )->create()->id,
            'source_content_hash' => hash('sha256', 'test source snapshot'),
            'excerpt_hash' => hash('sha256', 'test evidence excerpt'),
            'created_at' => now(),
        ];
    }

    public function forReviewAndEvidence(BrandClaimReviewEvent $event, BrandClaimEvidence $evidence): static
    {
        return $this->state(fn () => [
            'organisation_id' => $event->organisation_id, 'brand_id' => $event->brand_id,
            'review_event_id' => $event->id, 'claim_evidence_id' => $evidence->id,
            'source_content_hash' => $evidence->knowledgeSource->content_hash,
            'excerpt_hash' => hash('sha256', $evidence->supporting_excerpt),
        ]);
    }
}
