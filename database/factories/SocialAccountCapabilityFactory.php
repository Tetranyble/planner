<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\SocialAccountCapability;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialAccountCapability> */
final class SocialAccountCapabilityFactory extends Factory
{
    protected $model = SocialAccountCapability::class;

    public function definition(): array
    {
        return ['capability' => 'profile.read', 'mode' => 'READ_ONLY',
            'reason' => 'Synthetic test capability', 'checked_at' => now()];
    }

    public function forAccount(SocialAccount $account): static
    {
        return $this->state(fn () => [
            'organisation_id' => $account->organisation_id, 'brand_id' => $account->brand_id,
            'social_account_id' => $account->id,
        ]);
    }
}
