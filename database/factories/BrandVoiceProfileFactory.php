<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandVoiceProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandVoiceProfile> */
final class BrandVoiceProfileFactory extends Factory
{
    protected $model = BrandVoiceProfile::class;

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'organisation_id' => fn (array $attributes) => Brand::query()->findOrFail($attributes['brand_id'])->organisation_id,
            'name' => 'General '.$this->faker->unique()->word(), 'locale' => 'en-NG', 'tone_description' => 'Clear, professional and helpful', 'formality' => 3, 'warmth' => 4, 'directness' => 4, 'is_default' => false, 'active' => true,
        ];
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['brand_id' => $brand->id, 'organisation_id' => $brand->organisation_id]);
    }
}
