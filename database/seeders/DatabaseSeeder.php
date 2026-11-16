<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Production-safe bootstrap; fictional demonstration accounts are opt-in only. */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([DefaultSettingBootstrapper::class]);

        if (app()->environment(['local', 'testing']) && config('planner.seed_demo', false)) {
            $this->call([PlannerDevelopmentSeeder::class]);
        }
    }
}
