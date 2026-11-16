<?php

namespace Database\Factories;

use App\Models\BrandClaim;
use App\Models\BrandClaimReviewEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandClaimReviewEvent> */
final class BrandClaimReviewEventFactory extends Factory
{
    protected $model = BrandClaimReviewEvent::class;

    public function definition(): array
    {
        return [
            'claim_id' => BrandClaim::factory(),
            'organisation_id' => fn (array $a) => BrandClaim::query()->findOrFail($a['claim_id'])->organisation_id,
            'brand_id' => fn (array $a) => BrandClaim::query()->findOrFail($a['claim_id'])->brand_id,
            'reviewer_user_id' => User::factory(),
            'decision' => 'REJECT', 'rationale' => 'Unsupported demonstration claim requiring additional evidence.',
            'claim_hash' => fn (array $a) => hash('sha256', BrandClaim::query()->findOrFail($a['claim_id'])->claim_text),
            'created_at' => now(),
        ];
    }

    public function forClaim(BrandClaim $claim): static
    {
        return $this->state(fn () => [
            'organisation_id' => $claim->organisation_id,
            'brand_id' => $claim->brand_id,
            'claim_id' => $claim->id, 'claim_hash' => hash('sha256', $claim->claim_text),
        ]);
    }
}
