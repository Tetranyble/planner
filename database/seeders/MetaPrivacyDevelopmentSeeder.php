<?php

namespace Database\Seeders;

use App\Models\MetaDataDeletionRequest;
use App\Models\MetaDataDeletionTarget;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

/** Opt-in only; never create provider deletion test cases in production. */
final class MetaPrivacyDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! App::environment(['local', 'testing'])) {
            throw new \LogicException('Privacy sample data is restricted to local/testing environments.');
        }
        $org = Organisation::query()->where('name', 'Planner Privacy Fixture')->first();
        if (! $org) {
            $org = Organisation::factory()->create(['name' => 'Planner Privacy Fixture']);
        }
        $connection = ProviderConnection::query()->where('organisation_id', $org->id)
            ->where('external_actor_id', 'fixture-only-meta-actor')->first();
        if (! $connection) {
            $connection = ProviderConnection::factory()->forOrganisation($org)
                ->forProvider('facebook')->create(['external_actor_id' => 'fixture-only-meta-actor', 'status' => 'REVOKED']);
        }
        $request = MetaDataDeletionRequest::query()->where('actor_digest', hash('sha256', 'fixture-privacy-request'))->first();
        if (! $request) {
            $request = MetaDataDeletionRequest::factory()->create([
                'actor_digest' => hash('sha256', 'fixture-privacy-request'),
                'state' => 'NEEDS_REVIEW', 'matched_grants' => 1, 'processed_grants' => 1,
            ]);
        }
        MetaDataDeletionTarget::query()->firstOrCreate([
            'request_id' => $request->id, 'provider_connection_id' => $connection->id,
        ], [
            'organisation_id' => $org->id, 'state' => 'NEEDS_REVIEW', 'review_reason' => 'BRAND_CONTENT_REVIEW',
        ]);
    }
}
