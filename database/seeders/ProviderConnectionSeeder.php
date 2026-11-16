<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\ProviderConnection;
use Illuminate\Database\Seeder;

final class ProviderConnectionSeeder extends Seeder
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
        if ($org === null) {
            return;
        }
        ProviderConnection::query()->firstOrCreate(
            ['organisation_id' => $org->id, 'provider' => 'tiktok', 'external_actor_id' => 'fixture-not-authorized'],
            ProviderConnection::factory()->forOrganisation($org)->make([
                'provider' => 'tiktok', 'external_actor_id' => 'fixture-not-authorized',
            ])->only(['status', 'access_token_secret_ref', 'refresh_token_secret_ref', 'scopes']),
        );
    }
}
