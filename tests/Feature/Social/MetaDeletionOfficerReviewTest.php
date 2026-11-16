<?php

namespace Tests\Feature\Social;

use App\Models\MetaDataDeletionRequest;
use App\Models\MetaDataDeletionTarget;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Exercised with Kinship, its seeded owner role, real MySQL and model factories. */
final class MetaDeletionOfficerReviewTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithTarget(string $organisationName): array
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/v1/organisations', ['name' => $organisationName])->assertCreated();
        $org = Organisation::query()->where('name', $organisationName)->firstOrFail();
        $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider('facebook')->create(['status' => 'REVOKED']);
        $privacy = MetaDataDeletionRequest::factory()->create([
            'state' => 'NEEDS_REVIEW', 'matched_grants' => 1, 'processed_grants' => 1,
        ]);
        $target = MetaDataDeletionTarget::factory()->forConnection($connection, $privacy)->create([
            'state' => 'NEEDS_REVIEW', 'review_reason' => 'BRAND_CONTENT_REVIEW',
        ]);

        return [$user, $org, $privacy, $target];
    }

    public function test_only_authenticated_owner_can_review_a_tenant_scoped_deletion(): void
    {
        [$owner, $org, $privacy, $target] = $this->ownerWithTarget('Privacy Scope A');
        [$other, $otherOrg] = $this->ownerWithTarget('Privacy Scope B');
        $url = "/api/v1/organisations/{$org->uuid}/privacy/deletion-reviews/{$target->id}/resolve";
        $payload = [
            'disposition' => 'CLEARED',
            'explanation' => 'Reviewed source data and verified all attributable personal information removed.',
            'evidence_reference' => 'privacy-case/A-2026-001',
        ];
        $this->actingAs($other)->postJson($url, $payload)->assertForbidden();
        $this->assertSame('NEEDS_REVIEW', $target->fresh()->state);
        $this->actingAs($owner)->postJson($url, $payload)->assertOk();
        $this->assertSame('COMPLETED', $privacy->fresh()->state);
        $this->actingAs($owner)->postJson($url, $payload)->assertStatus(409);
    }

    public function test_privacy_queue_is_not_visible_to_another_organisation_owner(): void
    {
        [$owner, $org, , $target] = $this->ownerWithTarget('Privacy Queue A');
        [$other, $otherOrg] = $this->ownerWithTarget('Privacy Queue B');
        $response = $this->actingAs($other)->getJson(
            "/api/v1/organisations/{$otherOrg->uuid}/privacy/deletion-reviews"
        )->assertOk();
        $this->assertNotContains($target->id, array_column($response->json('data'), 'id'));
    }
}
