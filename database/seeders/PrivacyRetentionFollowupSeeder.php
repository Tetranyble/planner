<?php

namespace Database\Seeders;

use App\Repository\Contracts\PrivacyRetentionFollowupRepositoryContract;
use Illuminate\Database\Seeder;

/** Opt-in ONLY. Never manufacture legal decisions in development seeders. */
final class PrivacyRetentionFollowupSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Privacy follow-up seeding is restricted to local/testing.');
        }
        app(PrivacyRetentionFollowupRepositoryContract::class)->enqueueDue(200);
    }
}
