<?php

namespace Tests\Feature\Social;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GrantReconciliationHttpTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): array
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => 'Grant Reconciliation Org'])->assertCreated();
        $organisation = Organisation::query()->where('name', 'Grant Reconciliation Org')->firstOrFail();

        return [$owner, $organisation];
    }

    public function test_route_requires_authenticated_organisation_owner_and_kinship_permission(): void
    {
        [$owner,$org] = $this->owner();
        $this->getJson("/api/v1/organisations/{$org->uuid}/grant-reconciliations")->assertOk();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->getJson("/api/v1/organisations/{$org->uuid}/grant-reconciliations")
            ->assertForbidden();
    }

    public function test_malformed_evidence_is_rejected_before_repository_invocation(): void
    {
        [$owner,$org] = $this->owner();
        $this->actingAs($owner)->postJson(
            "/api/v1/organisations/{$org->uuid}/grant-revocations/".fake()->uuid().'/reconciliation-evidence',
            ['source_type' => 'PROVIDER_SUPPORT', 'reference' => 'access_token=secret', 'summary' => 'short'],
        )->assertUnprocessable()->assertJsonValidationErrors(['reference', 'summary']);
    }
}
