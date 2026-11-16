<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandKnowledgeSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandKnowledgeSource> */
final class BrandKnowledgeSourceFactory extends Factory
{
    protected $model = BrandKnowledgeSource::class;

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'organisation_id' => fn (array $attributes) => Brand::query()->findOrFail($attributes['brand_id'])->organisation_id,
            'title' => 'Unverified internal reference', 'source_type' => 'MANUAL', 'source_url' => null, 'object_key' => null, 'content_text' => null, 'content_hash' => hash('sha256', $this->faker->unique()->sentence()), 'trust_status' => 'PENDING', 'retrieved_at' => null, 'verified_at' => null, 'verified_by_user_id' => null,
        ];
    }

    public function trusted(): static
    {
        return $this->state(fn () => [
            'trust_status' => 'TRUSTED', 'verified_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'trust_status' => 'REJECTED', 'verified_at' => now(),
        ]);
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['brand_id' => $brand->id, 'organisation_id' => $brand->organisation_id]);
    }
}
