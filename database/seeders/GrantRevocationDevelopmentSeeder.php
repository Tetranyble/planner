<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\ProviderGrantGroup;
use App\Models\ProviderGrantLink;
use App\Models\ProviderGrantRevocationRequest;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Opt-in only: php artisan db:seed --class=GrantRevocationDevelopmentSeeder */
final class GrantRevocationDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Revocation fixtures must not be seeded outside local/testing.');
        }
        if (Organisation::query()->where('name', 'Planner Grant Revocation Fixture')->exists()) {
            return;
        }
        $owner = User::factory()->create();
        $org = Organisation::factory()->create(['name' => 'Planner Grant Revocation Fixture']);
        $brand = Brand::factory()->forOrganisation($org)->create(['name' => 'Fixture Marketing Brand']);
        $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider('tiktok')
            ->create(['status' => 'CONNECTED']);
        ProviderConnectionCredential::factory()->forConnection($connection)->create();
        SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create(['status' => 'ACTIVE']);
        $group = ProviderGrantGroup::factory()->create(['provider_family' => 'tiktok',
            'application_id' => 'synthetic-only-fixture']);
        ProviderGrantLink::factory()->forConnection($connection)->create(['grant_group_id' => $group->id]);
        ProviderGrantRevocationRequest::factory()->forConnection($connection, $group, $owner)->create();
        $this->command?->info('Generated one inert PREPARED sample; no outbound authorization request will run.');
    }
}
