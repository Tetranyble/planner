<?php

namespace Database\Factories;

use App\Models\ApiRateLimitBucket;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiRateLimitBucket>
 */
class ApiRateLimitBucketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'tiktok',
            'bucket_key' => 'fixture-'.Str::random(16),
            'last_observed_at' => now(),
        ];
    }

    public function forOrganisation(Organisation $organisation): static
    {
        return $this->state(fn (): array => ['organisation_id' => $organisation->id]);
    }
}
