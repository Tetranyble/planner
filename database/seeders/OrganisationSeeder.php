<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\User;
use App\Services\Onboarding\CreateOrganisationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

/** Safe, idempotent demo workspace. Never enabled as part of production seeding. */
final class OrganisationSeeder extends Seeder
{
    public const DEMO_EMAIL = 'planner-demo-owner@example.test';

    public const DEMO_ORG = 'Planner Example Organisation';

    public function run(CreateOrganisationService $onboarding): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo organisation seeding is restricted to local/testing environments.');
        }

        if (Organisation::query()->where('name', self::DEMO_ORG)->exists()) {
            return;
        }

        $owner = User::query()->firstOrCreate(['email' => self::DEMO_EMAIL], [
            'name' => 'Example Owner',
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(64)), // Never distribute a shared/default password.
        ]);
        $previous = auth()->user();
        auth()->setUser($owner);
        try {
            $onboarding->execute($owner, self::DEMO_ORG, 'Africa/Lagos');
        } finally {
            if ($previous instanceof User) {
                auth()->setUser($previous);
            } else {
                auth()->forgetUser();
            }
        }
    }
}
