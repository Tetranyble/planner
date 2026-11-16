<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandVoiceProfile;
use App\Models\BrandVoiceTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandVoiceTerm> */
final class BrandVoiceTermFactory extends Factory
{
    protected $model = BrandVoiceTerm::class;

    public function definition(): array
    {
        return [
            'voice_profile_id' => BrandVoiceProfile::factory(),
            'brand_id' => fn (array $attributes) => BrandVoiceProfile::query()->findOrFail($attributes['voice_profile_id'])->brand_id,
            'organisation_id' => fn (array $attributes) => BrandVoiceProfile::query()->findOrFail($attributes['voice_profile_id'])->organisation_id,
            'term' => 'effortless', 'usage' => 'AVOID', 'replacement_term' => null, 'rationale' => 'Avoid absolute marketing promises',
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
