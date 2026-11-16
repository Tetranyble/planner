<?php

namespace Database\Seeders;

use App\Models\SocialAccount;
use App\Models\SocialAccountStatusEvent;
use Illuminate\Database\Seeder;

final class SocialAccountStatusEventSeeder extends Seeder
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
        SocialAccountStatusEvent::query()->firstOrCreate([
            'organisation_id' => $account->organisation_id,
            'brand_id' => $account->brand_id, 'social_account_id' => $account->id,
            'event_type' => 'SEEDED_DEMO',
        ], SocialAccountStatusEvent::factory()->forAccount($account)->make([
            'event_type' => 'SEEDED_DEMO', 'old_status' => null, 'new_status' => 'ARCHIVED',
        ])->only(['old_status', 'new_status', 'recorded_at']));
    }
}
