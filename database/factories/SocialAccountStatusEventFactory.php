<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\SocialAccountStatusEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialAccountStatusEvent> */
final class SocialAccountStatusEventFactory extends Factory
{
    protected $model = SocialAccountStatusEvent::class;

    public function definition(): array
    {
        return ['event_type' => 'DISCONNECTED_LOCALLY', 'old_status' => 'ACTIVE',
            'new_status' => 'ARCHIVED', 'recorded_at' => now()];
    }

    public function forAccount(SocialAccount $account): static
    {
        return $this->state(fn () => [
            'organisation_id' => $account->organisation_id, 'brand_id' => $account->brand_id,
            'social_account_id' => $account->id,
        ]);
    }
}
