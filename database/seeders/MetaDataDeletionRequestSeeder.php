<?php

namespace Database\Seeders;

use App\Models\MetaDataDeletionRequest;
use Illuminate\Database\Seeder;

/** Opt-in synthetic status example. Never insert provider privacy requests into production. */
final class MetaDataDeletionRequestSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Privacy demonstration seeding is development-only.');
        }
        MetaDataDeletionRequest::query()->firstOrCreate(
            ['signed_request_digest' => hash('sha256', 'planner:meta-deletion:synthetic-demo')],
            [
                'uuid' => 'a2222222-2222-4222-8222-222222222222',
                'actor_digest' => hash('sha256', 'synthetic-only'),
                'actor_ciphertext' => null,
                'confirmation_code' => 'ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789',
                'state' => 'COMPLETED', 'accepted_at' => now(), 'completed_at' => now(),
                'matched_grants' => 0, 'processed_grants' => 0,
            ]
        );
    }
}
