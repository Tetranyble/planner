<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LogicException;

/** Opt-in only: php artisan db:seed --class=PlannerDevelopmentSeeder */
final class PlannerDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Planner demonstration data is prohibited outside local/testing.');
        }
        $this->call([OrganisationSeeder::class, BrandSeeder::class, ContentStudioDevelopmentSeeder::class]);
    }
}
