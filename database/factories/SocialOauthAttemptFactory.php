<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\SocialOauthAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialOauthAttempt> */
final class SocialOauthAttemptFactory extends Factory
{
    protected $model = SocialOauthAttempt::class;

    public function definition(): array
    {
        return [
            'state_digest' => hash('sha256', str()->random(64)),
            'provider' => 'tiktok', 'redirect_uri' => 'https://example.test/integrations/social/tiktok/callback',
            'expires_at' => now()->addMinutes(10), 'consumed_at' => null,
        ];
    }

    public function forBrandAndActor(Brand $brand, User $user): static
    {
        return $this->state(fn () => [
            'organisation_id' => $brand->organisation_id,
            'brand_id' => $brand->id, 'user_id' => $user->id,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }

    public function consumed(): static
    {
        return $this->state(fn () => ['consumed_at' => now()->subSecond()]);
    }
}
