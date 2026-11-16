<?php

namespace Database\Seeders;

use App\Models\MetaDataDeletionRequest;
use App\Models\MetaDataDeletionTarget;
use App\Models\ProviderConnection;
use Illuminate\Database\Seeder;

/** Opt-in synthetic example; never creates or mutates real provider authorizations. */
final class MetaDataDeletionTargetSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Privacy demonstration seeding is development-only.');
        }
        $request = MetaDataDeletionRequest::query()->where('signed_request_digest',
            hash('sha256', 'planner:meta-deletion:synthetic-demo'))->first();
        $connection = ProviderConnection::query()->where('external_actor_id', 'fixture-meta-demo')->first();
        if ($request !== null && $connection !== null) {
            MetaDataDeletionTarget::query()->firstOrCreate([
                'request_id' => $request->id, 'provider_connection_id' => $connection->id,
            ], ['organisation_id' => $connection->organisation_id, 'state' => 'COMPLETED', 'processed_at' => now()]);
        }
    }
}
