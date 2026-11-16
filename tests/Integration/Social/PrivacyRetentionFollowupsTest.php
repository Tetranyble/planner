<?php

namespace Tests\Integration\Social;

use App\Models\MetaDataDeletionRequest;
use App\Models\MetaDataDeletionReviewDecision;
use App\Models\MetaDataDeletionTarget;
use App\Models\Organisation;
use App\Models\PrivacyRetentionFollowup;
use App\Models\ProviderConnection;
use App\Models\User;
use App\Repository\Contracts\PrivacyRetentionFollowupRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

final class PrivacyRetentionFollowupsTest extends TestCase
{
    use RefreshDatabase;

    private function retained(Organisation $organisation, string $date): MetaDataDeletionReviewDecision
    {
        $connection = ProviderConnection::factory()->forOrganisation($organisation)->forProvider('facebook')->create();
        $request = MetaDataDeletionRequest::factory()->create(['state' => 'COMPLETED_WITH_RETENTION']);
        $target = MetaDataDeletionTarget::factory()->forConnection($connection, $request)
            ->create(['state' => 'RETAINED_LEGAL']);

        return MetaDataDeletionReviewDecision::factory()->forTarget($target)->create([
            'disposition' => 'RETAINED_LEGAL', 'legal_basis' => 'LEGAL_CLAIMS', 'review_again_at' => $date,
        ]);
    }

    public function test_due_retention_is_enqueued_once_and_is_tenant_scoped(): void
    {
        $org = Organisation::factory()->create();
        $other = Organisation::factory()->create();
        $due = $this->retained($org, now()->subDay()->toDateTimeString());
        $this->retained($other, now()->addDays(20)->toDateTimeString());
        $repo = app(PrivacyRetentionFollowupRepositoryContract::class);
        $this->assertSame(1, $repo->enqueueDue(20));
        $this->assertSame(0, $repo->enqueueDue(20));
        $this->assertSame(1, PrivacyRetentionFollowup::query()->count());
        $this->assertCount(1, $repo->directory($org));
        $this->assertCount(0, $repo->directory($other));
        $this->assertSame($due->id, PrivacyRetentionFollowup::query()->firstOrFail()->review_decision_id);
    }

    public function test_acknowledgement_does_not_change_original_legal_decision(): void
    {
        $org = Organisation::factory()->create();
        $decision = $this->retained($org, now()->subDays(2)->toDateTimeString());
        $repo = app(PrivacyRetentionFollowupRepositoryContract::class);
        $repo->enqueueDue(20);
        $followup = PrivacyRetentionFollowup::query()->firstOrFail();
        $user = User::factory()->create();
        $repo->acknowledge($org, $user, $followup->id, 'Re-examined the basis and recorded the compliance follow-up ticket.');
        $this->assertSame('ACKNOWLEDGED', $followup->fresh()->status);
        $this->assertSame('RETAINED_LEGAL', $decision->fresh()->disposition);
        $this->expectException(ConflictHttpException::class);
        $repo->acknowledge($org, $user, $followup->id, 'Duplicate acknowledgement must be rejected.');
    }
}
