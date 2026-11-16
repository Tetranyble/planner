<?php

namespace Tests\Integration\Content;

use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\ContentVersion;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use App\Repository\Contracts\PublicationReconciliationRepositoryContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/** Intended for a disposable MySQL 8 database; not validated in this environment. */
final class PublicationReconciliationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $version = ContentVersion::factory()->forItem($item)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create();
        $target = ContentTarget::factory()->forVersionAndAccount($version, $account)->create();
        $policy = BrandApprovalPolicy::factory()->forBrand($brand)->create();
        $approval = ApprovalRequest::factory()->forTarget($target, $policy, User::factory()->create())->create();
        $job = app(PublicationIntentRepositoryContract::class)->createHeld($org, $brand, $target, $approval,
            'reconciliation-integration-fixture', '2027-01-01 12:00:00');
        $job->forceFill(['state' => 'UNCERTAIN'])->save();

        return [$org, $brand, $job];
    }

    public function test_evidence_and_independent_decision_do_not_mutate_publication_or_dispatch(): void
    {
        [$org,$brand,$job] = $this->fixture();
        $submitter = User::factory()->create();
        $reviewer = User::factory()->create();
        $repo = app(PublicationReconciliationRepositoryContract::class);
        $evidence = $repo->submit($org, $brand, $job->id, $submitter->id, [
            'evidence_kind' => 'PROVIDER_POST_LOOKUP', 'evidence_sha256' => str_repeat('a', 64),
            'summary' => 'Platform post-search outcome was inconclusive.']);
        $this->assertSame('RECORDED', $evidence['status']);
        $this->assertDatabaseCount('publication_reconciliation_evidence', 1);
        $this->expectException(ConflictHttpException::class);
        $repo->decide($org, $brand, $job->id, $submitter->id, [
            'evidence_id' => $evidence['id'], 'disposition' => 'ESCALATED',
            'rationale' => 'Same person must not review own evidence.']);
    }

    public function test_review_is_once_only_and_remains_uncertain(): void
    {
        [$org,$brand,$job] = $this->fixture();
        $submitter = User::factory()->create();
        $reviewer = User::factory()->create();
        $repo = app(PublicationReconciliationRepositoryContract::class);
        $evidence = $repo->submit($org, $brand, $job->id, $submitter->id, [
            'evidence_kind' => 'AUDIT_EXPORT', 'evidence_sha256' => str_repeat('b', 64),
            'summary' => 'Hash of independently collected activity export.']);
        $decision = $repo->decide($org, $brand, $job->id, $reviewer->id, [
            'evidence_id' => $evidence['id'], 'disposition' => 'ESCALATED', 'rationale' => 'Additional remote evidence is required.']);
        $this->assertSame('UNCERTAIN', $decision['publication_state']);
        $this->assertSame('UNCERTAIN', $job->fresh()->state);
        $this->assertDatabaseCount('publication_reconciliation_decisions', 1);
        $this->assertDatabaseCount('publication_events', 2);
        $this->expectException(ConflictHttpException::class);
        $repo->decide($org, $brand, $job->id, $reviewer->id, [
            'evidence_id' => $evidence['id'], 'disposition' => 'PROVIDER_CONFIRMED', 'rationale' => 'Repeated decision should be blocked.']);
    }

    public function test_cross_brand_reconciliation_fails_closed(): void
    {
        [$org,$brand,$job] = $this->fixture();
        $otherBrand = Brand::factory()->forOrganisation($org)->create();
        $repo = app(PublicationReconciliationRepositoryContract::class);
        $this->expectException(ModelNotFoundException::class);
        $repo->history($org,$otherBrand,$job->id);
    }
}
