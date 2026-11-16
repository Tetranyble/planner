<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Explicit opt-in: php artisan db:seed --class=SocialConnectionsDevelopmentSeeder */
final class SocialConnectionsDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Do not seed fictional social accounts in production.');
        }
        $this->call([
            ProviderConnectionSeeder::class,
            SocialAccountSeeder::class,
            SocialAccountCapabilitySeeder::class,
            SocialAccountStatusEventSeeder::class,
        ]);
    }
}
