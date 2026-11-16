<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandClaim;
use App\Models\BrandClaimEvidence;
use App\Models\BrandKnowledgeSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandClaimEvidence> */
final class BrandClaimEvidenceFactory extends Factory
{
    protected $model = BrandClaimEvidence::class;

    public function definition(): array
    {
        return [
            'claim_id' => BrandClaim::factory(),
            'brand_id' => fn (array $attributes) => BrandClaim::query()->findOrFail($attributes['claim_id'])->brand_id,
            'organisation_id' => fn (array $attributes) => BrandClaim::query()->findOrFail($attributes['claim_id'])->organisation_id,
            'knowledge_source_id' => fn (array $a) => BrandKnowledgeSource::factory()->forBrand(Brand::query()->findOrFail($a['brand_id']))->create()->id, 'supporting_excerpt' => 'Unreviewed supporting passage', 'source_locator' => null, 'reviewed_by_user_id' => null, 'reviewed_at' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'review_state' => 'VERIFIED', 'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'review_state' => 'REJECTED', 'reviewed_at' => now(),
        ]);
    }

    public function forBrandClaim(BrandClaim $parent): static
    {
        return $this->state(fn () => ['claim_id' => $parent->id, 'brand_id' => $parent->brand_id, 'organisation_id' => $parent->organisation_id]);
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['claim_id' => BrandClaim::factory()->forBrand($brand),
            'brand_id' => $brand->id, 'organisation_id' => $brand->organisation_id]);
    }
}
