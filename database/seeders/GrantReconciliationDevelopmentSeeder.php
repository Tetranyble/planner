<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderGrantGroup;
use App\Models\ProviderGrantLink;
use App\Models\ProviderGrantReconciliationDecision;
use App\Models\ProviderGrantReconciliationEvidence;
use App\Models\ProviderGrantRevocationRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Explicitly opt-in synthetic fixture; never included in production DatabaseSeeder. */
final class GrantReconciliationDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Reconciliation fixtures may only be seeded in local/testing.');
        }
        if (Organisation::query()->where('name', 'Planner Reconciliation Fixture')->exists()) {
            return;
        }

        $owner = User::factory()->create();
        $org = Organisation::factory()->create(['name' => 'Planner Reconciliation Fixture']);
        $brand = Brand::factory()->forOrganisation($org)->create(['name' => 'Synthetic Social Brand']);
        $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider('tiktok')
            ->create(['external_actor_id' => 'synthetic-reconciliation-actor', 'status' => 'RESTRICTED']);
        $group = ProviderGrantGroup::factory()->create([
            'provider_family' => 'tiktok', 'application_id' => 'fixture-app-only',
        ]);
        ProviderGrantLink::factory()->forConnection($connection)->create(['grant_group_id' => $group->id]);
        $request = ProviderGrantRevocationRequest::factory()->forConnection($connection, $group, $owner)
            ->create(['status' => 'MANUAL_REQUIRED', 'safe_error_code' => 'DEMO_ONLY']);
        $evidence = ProviderGrantReconciliationEvidence::factory()->forRequest($request, $owner)->create([
            'reference' => 'TEST-CASE-RECONCILIATION-ONLY',
            'summary' => 'Synthetic provider support evidence used only for development of the independent review desk.',
        ]);
        $reviewer = User::factory()->create();
        ProviderGrantReconciliationDecision::factory()->forEvidence($evidence, $reviewer)->create([
            'outcome' => 'ESCALATED',
            'rationale' => 'Synthetic second-reviewer escalation does not authorize an outbound provider call.',
        ]);
        $this->command?->info('Created one isolated, synthetic manual-required case. No provider calls were made.');
    }
}
