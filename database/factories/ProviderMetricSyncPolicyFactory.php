<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ProviderMetricSyncPolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProviderMetricSyncPolicy> */
final class ProviderMetricSyncPolicyFactory extends Factory
{
    protected $model = ProviderMetricSyncPolicy::class;

    public function definition(): array
    {
        return ['enabled' => false, 'utc_hour' => 8, 'max_posts_per_day' => 10, 'version' => 1];
    }

    public function forBrand(Brand $brand, User $editor): static
    {
        return $this->state(fn () => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id,
            'configured_by_user_id' => $editor->id]);
    }
}
