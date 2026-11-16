<?php

namespace Database\Seeders;

use App\Models\SocialAccount;
use App\Models\SocialAccountCapability;
use Illuminate\Database\Seeder;

final class SocialAccountCapabilitySeeder extends Seeder
{
    private function preventProductionSeed(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Synthetic social account data may only be seeded locally.');
        }
    }

    public function run(): void
    {
        $this->preventProductionSeed();
        $account = SocialAccount::query()->where('external_account_id', 'fixture-not-authorized')
            ->where('provider', 'tiktok')->first();
        if ($account === null) {
            return;
        }
        SocialAccountCapability::query()->firstOrCreate([
            'organisation_id' => $account->organisation_id, 'social_account_id' => $account->id,
            'capability' => 'video.publish',
        ], SocialAccountCapability::factory()->forAccount($account)->make([
            'capability' => 'video.publish', 'mode' => 'UNAVAILABLE',
            'reason' => 'Demonstration account has no provider credentials',
        ])->only(['brand_id', 'mode', 'reason', 'checked_at']));
    }
}
