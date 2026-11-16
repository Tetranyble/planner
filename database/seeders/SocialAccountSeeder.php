<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\SocialAccount;
use Illuminate\Database\Seeder;

final class SocialAccountSeeder extends Seeder
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
        $org = Organisation::query()->orderBy('id')->first();
        $brand = $org?->brands()->orderBy('id')->first();
        if ($org === null || $brand === null) {
            return;
        }
        $connection = ProviderConnection::query()->where('organisation_id', $org->id)
            ->where('provider', 'tiktok')->where('external_actor_id', 'fixture-not-authorized')->first();
        if ($connection === null) {
            return;
        }
        SocialAccount::query()->firstOrCreate(
            ['organisation_id' => $org->id, 'provider' => 'tiktok', 'external_account_id' => 'fixture-not-authorized'],
            SocialAccount::factory()->forBrandAndConnection($brand, $connection)->make([
                'display_name' => 'Demo TikTok (not connected)', 'external_account_id' => 'fixture-not-authorized',
            ])->only(['brand_id', 'provider_connection_id', 'account_kind', 'display_name', 'status']),
        );
    }
}
