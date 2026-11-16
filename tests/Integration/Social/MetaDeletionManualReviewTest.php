<?php

namespace Tests\Integration\Social;

use App\Domain\Privacy\MetaReviewDisposition;
use App\Models\MetaDataDeletionRequest;
use App\Models\MetaDataDeletionReviewDecision;
use App\Models\MetaDataDeletionTarget;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\User;
use App\Repository\Contracts\MetaDeletionReviewRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

final class MetaDeletionManualReviewTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organisation::factory()->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider('facebook')->create(['status' => 'REVOKED']);
        $request = MetaDataDeletionRequest::factory()->create([
            'state' => 'NEEDS_REVIEW', 'matched_grants' => 1, 'processed_grants' => 1,
        ]);
        $target = MetaDataDeletionTarget::factory()->forConnection($connection, $request)
            ->create(['state' => 'NEEDS_REVIEW', 'review_reason' => 'BRAND_CONTENT_REVIEW']);

        return [$org, $request, $target, User::factory()->create()];
    }

    public function test_review_is_immutable_and_completes_only_its_own_request(): void
    {
        [$org,$request,$target,$reviewer] = $this->fixture();
        $repository = app(MetaDeletionReviewRepositoryContract::class);
        $decision = MetaReviewDisposition::validate([
            'disposition' => 'CLEARED', 'explanation' => 'Reviewed all provider-derived and business-authored personal data references.',
            'evidence_reference' => 'privacy-case-2026-101',
        ]);
        DB::transaction(function () use ($repository, $org, $target, $reviewer, $decision): void {
            $locked = $repository->lockReviewTarget($org, $target->id);
            $repository->resolve($locked, $reviewer, $decision);
        });
        $this->assertSame('COMPLETED', $request->fresh()->state);
        $this->assertSame('COMPLETED', $target->fresh()->state);
        $this->assertSame(1, MetaDataDeletionReviewDecision::query()->count());
        $this->expectException(ConflictHttpException::class);
        DB::transaction(function () use ($repository, $org, $target, $reviewer, $decision): void {
            $repository->resolve($repository->lockReviewTarget($org, $target->id), $reviewer, $decision);
        });
    }

    public function test_retention_is_truthfully_reported_not_mislabeled_as_erasure(): void
    {
        [$org,$request,$target,$reviewer] = $this->fixture();
        $decision = MetaReviewDisposition::validate([
            'disposition' => 'RETAINED_LEGAL',
            'explanation' => 'A narrow evidence record is under an identified legal claims hold until reviewed.',
            'evidence_reference' => 'legal-hold-PRIV-2026-102',
            'legal_basis' => 'LEGAL_CLAIMS', 'review_again_at' => now()->addMonths(6)->format('Y-m-d'),
        ]);
        DB::transaction(function () use ($org, $target, $reviewer, $decision): void {
            $repo = app(MetaDeletionReviewRepositoryContract::class);
            $repo->resolve($repo->lockReviewTarget($org, $target->id), $reviewer, $decision);
        });
        $this->assertSame('COMPLETED_WITH_RETENTION', $request->fresh()->state);
        $this->assertSame('RETAINED_LEGAL', $target->fresh()->state);
    }

    public function test_review_cannot_mark_an_active_grant_as_erased(): void
    {
        [$org,$request,$target,$reviewer] = $this->fixture();
        ProviderConnection::query()->whereKey($target->provider_connection_id)->update(['status' => 'CONNECTED']);
        $decision = MetaReviewDisposition::validate([
            'disposition' => 'CLEARED',
            'explanation' => 'Attempt to attest deletion while a usable provider grant still exists.',
            'evidence_reference' => 'privacy-case-failed-001',
        ]);
        $this->expectException(ConflictHttpException::class);
        DB::transaction(function () use ($org, $target, $reviewer, $decision): void {
            $repo = app(MetaDeletionReviewRepositoryContract::class);
            $repo->resolve($repo->lockReviewTarget($org, $target->id), $reviewer, $decision);
        });
    }
}
