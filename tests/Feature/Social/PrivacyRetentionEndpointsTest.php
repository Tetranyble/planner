<?php

namespace Tests\Feature\Social;

use App\Models\MetaDataDeletionRequest;
use App\Models\MetaDataDeletionReviewDecision;
use App\Models\MetaDataDeletionTarget;
use App\Models\Organisation;
use App\Models\PrivacyRetentionFollowup;
use App\Models\ProviderConnection;
use App\Models\User;
use App\Repository\Contracts\PrivacyRetentionFollowupRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PrivacyRetentionEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(string $name): array
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/v1/organisations', ['name' => $name])->assertCreated();

        return [$user, Organisation::query()->where('name', $name)->firstOrFail()];
    }

    private function pendingFollowup(Organisation $org): PrivacyRetentionFollowup
    {
        $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider('facebook')->create(['status' => 'REVOKED']);
        $request = MetaDataDeletionRequest::factory()->create(['state' => 'COMPLETED_WITH_RETENTION']);
        $target = MetaDataDeletionTarget::factory()->forConnection($connection, $request)->create(['state' => 'RETAINED_LEGAL']);
        $decision = MetaDataDeletionReviewDecision::factory()->forTarget($target)->create([
            'disposition' => 'RETAINED_LEGAL', 'legal_basis' => 'LEGAL_CLAIMS',
            'review_again_at' => now()->subDay(),
        ]);
        app(PrivacyRetentionFollowupRepositoryContract::class)->enqueueDue(10);

        return PrivacyRetentionFollowup::query()->where('review_decision_id', $decision->id)->firstOrFail();
    }

    public function test_owner_can_acknowledge_due_followup_without_changing_legal_decision(): void
    {
        [$user,$org] = $this->owner('Retention Followup Org');
        $item = $this->pendingFollowup($org);
        $route = "/api/v1/organisations/{$org->uuid}/privacy/retention-reviews/{$item->id}/acknowledge";
        $this->actingAs($user)->postJson($route, [
            'notes' => 'I verified the open legal hold and documented the reassessment in our case register.',
        ])->assertOk();
        $this->assertSame('ACKNOWLEDGED', $item->fresh()->status);
        $this->assertSame('RETAINED_LEGAL', MetaDataDeletionReviewDecision::query()->findOrFail($item->review_decision_id)->disposition);
        $this->actingAs($user)->postJson($route, ['notes' => 'A second review must not overwrite the first record.'])->assertStatus(409);
    }

    public function test_other_organisation_owner_cannot_read_or_acknowledge_a_due_review(): void
    {
        [$user,$org] = $this->owner('Retention Scope Alpha');
        $item = $this->pendingFollowup($org);
        [$outsider,$otherOrg] = $this->owner('Retention Scope Beta');
        $this->actingAs($outsider)->getJson("/api/v1/organisations/{$otherOrg->uuid}/privacy/retention-reviews")
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($outsider)->postJson(
            "/api/v1/organisations/{$org->uuid}/privacy/retention-reviews/{$item->id}/acknowledge",
            ['notes' => 'Attempt to access another organisation privacy review from a separate workspace.'])
            ->assertForbidden();
        $this->assertSame('DUE', $item->fresh()->status);
    }
}
