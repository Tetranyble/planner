<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\SocialOAuthSelection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/** Only synthetic credentials in fixtures; production seeding MUST NOT create pending grants. */
final class SocialOAuthSelectionFactory extends Factory
{
    protected $model = SocialOAuthSelection::class;

    public function definition(): array
    {
        return [
            'provider' => 'facebook',
            'grant_ciphertext' => Crypt::encryptString(json_encode([
                'accessToken' => 'synthetic-access-token', 'refreshToken' => null,
                'actorId' => 'fixture-user', 'scopes' => ['pages_show_list'], 'expiresIn' => 3600,
                'refreshExpiresIn' => null,
            ], JSON_THROW_ON_ERROR)),
            'candidates_ciphertext' => Crypt::encryptString(json_encode([
                ['externalId' => 'fixture-page-a', 'kind' => 'PAGE', 'displayName' => 'Example Page A', 'handle' => null],
                ['externalId' => 'fixture-page-b', 'kind' => 'PAGE', 'displayName' => 'Example Page B', 'handle' => null],
            ], JSON_THROW_ON_ERROR)),
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
        ];
    }

    public function forBrandAndActor(Brand $brand, User $actor): static
    {
        return $this->state(fn () => [
            'organisation_id' => $brand->organisation_id,
            'brand_id' => $brand->id,
            'user_id' => $actor->id,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }
}
