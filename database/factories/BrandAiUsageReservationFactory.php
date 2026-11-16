<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandAiUsageReservation;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<BrandAiUsageReservation> */
final class BrandAiUsageReservationFactory extends Factory
{
    protected $model = BrandAiUsageReservation::class;

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'organisation_id' => Organisation::factory(),
            'brand_id' => Brand::factory(), 'requested_by_user_id' => User::factory(),
            'usage_day' => now('UTC')->toDateString(), 'purpose' => 'COPY', 'status' => 'RESERVED',
            'expires_at' => now('UTC')->addMinutes(10)];
    }
}
