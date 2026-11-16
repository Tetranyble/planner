<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandProfile> */
final class BrandProfileFactory extends Factory
{
    protected $model = BrandProfile::class;

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'organisation_id' => fn (array $attributes) => Brand::query()->findOrFail($attributes['brand_id'])->organisation_id,
            'positioning' => 'A clearer way to manage marketing across brands.', 'default_locale' => 'en-NG', 'updated_by_user_id' => null,
        ];
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['brand_id' => $brand->id, 'organisation_id' => $brand->organisation_id]);
    }
}
