<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandVoiceExample;
use App\Models\BrandVoiceProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandVoiceExample> */
final class BrandVoiceExampleFactory extends Factory
{
    protected $model = BrandVoiceExample::class;

    public function definition(): array
    {
        return [
            'voice_profile_id' => BrandVoiceProfile::factory(),
            'brand_id' => fn (array $attributes) => BrandVoiceProfile::query()->findOrFail($attributes['voice_profile_id'])->brand_id,
            'organisation_id' => fn (array $attributes) => BrandVoiceProfile::query()->findOrFail($attributes['voice_profile_id'])->organisation_id,
            'example_kind' => 'GOOD', 'example_text' => 'See how teams can organize tasks in one place', 'explanation' => 'Conservative capability phrasing',
        ];
    }

    public function forBrandVoiceProfile(BrandVoiceProfile $parent): static
    {
        return $this->state(fn () => ['voice_profile_id' => $parent->id, 'brand_id' => $parent->brand_id, 'organisation_id' => $parent->organisation_id]);
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['voice_profile_id' => BrandVoiceProfile::factory()->forBrand($brand),
            'brand_id' => $brand->id, 'organisation_id' => $brand->organisation_id]);
    }
}
