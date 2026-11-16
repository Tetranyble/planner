<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\SocialMetricSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialMetricSnapshot> */
final class SocialMetricSnapshotFactory extends Factory
{
    protected $model = SocialMetricSnapshot::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'brand_id' => fn (array $attributes) => Brand::factory()->forOrganisation(Organisation::query()->findOrFail($attributes['organisation_id']))->create()->id,
            'submitted_by_user_id' => User::factory(), 'provider' => 'instagram',
            'account_reference' => 'test-page-1', 'external_post_reference' => 'test-post-1',
            'observed_at' => now()->utc(), 'impressions' => 100, 'reach' => 80, 'likes' => 10,
            'comments' => 2, 'shares' => 1, 'clicks' => 3, 'source_type' => 'MANUAL_EXPORT',
            'source_reference' => 'test-fixture', 'submission_key' => hash('sha256', $this->faker->uuid()),
            'payload_hash' => hash('sha256', $this->faker->uuid()),
        ];
    }
}
