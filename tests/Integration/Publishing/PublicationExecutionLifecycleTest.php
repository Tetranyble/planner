<?php

namespace Tests\Integration\Publishing;

use App\Domain\Publishing\Delivery\DispatchOutcome;
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
use App\Repository\Contracts\PublicationExecutionRepositoryContract;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/** Requires disposable MySQL: real row locking, uniqueness, tenant relationships. */
final class PublicationExecutionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $version = ContentVersion::factory()->forItem($item)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create();
        $target = ContentTarget::factory()->forVersionAndAccount($version, $account)->create();
        $policy = BrandApprovalPolicy::factory()->forBrand($brand)->create();
        $approval = ApprovalRequest::factory()->forTarget($target, $policy, $actor)->create();
        $job = app(PublicationIntentRepositoryContract::class)->createHeld($org, $brand, $target, $approval,
            '57df5ca2-a714-4e60-9d68-2b6b1eed3541', now()->subMinutes(1)->format('Y-m-d H:i:s'));

        return [$org, $brand, $actor, $job];
    }

    public function test_arm_claim_receipt_and_duplicate_worker_are_fenced(): void
    {
        [$org,$brand,$actor,$job] = $this->fixture();
        $repo = app(PublicationExecutionRepositoryContract::class);
        $repo->arm($org, $brand, $job, $actor->id, 0);
        $this->assertSame('SCHEDULED', $job->fresh()->state);
        $this->assertContains($job->id, $repo->dueIds(10));
        $claim = $repo->claim($job->id, 'worker-token-1');
        $this->assertSame('worker-token-1', $claim['owner']);
        $this->assertNull($repo->claim($job->id, 'duplicate-worker'));
        $operation = $repo->startAttempt($job->id, 'worker-token-1');
        $this->assertNotNull($operation);
        $this->assertTrue($repo->attemptIsFenced($job->id, 'worker-token-1', $operation));
        $this->assertFalse($repo->attemptIsFenced($job->id, 'duplicate-worker', $operation));
        $this->assertNull($repo->startAttempt($job->id, 'worker-token-1'));
        $receipt = new DispatchOutcome('SUCCESS', 'PROVIDER_RECEIPT', 201, null, 'urn:li:share:123456');
        $this->assertTrue($repo->finishAttempt($job->id, 'worker-token-1', $operation, $receipt));
        $this->assertFalse($repo->finishAttempt($job->id, 'worker-token-1', $operation, $receipt));
        $this->assertSame('PUBLISHED', $job->fresh()->state);
        $this->assertDatabaseHas('publication_attempts', ['publication_job_id' => $job->id, 'result' => 'SUCCESS']);
        $this->assertDatabaseCount('publication_events', 4);
    }

    public function test_uncertain_result_never_returns_to_due_queue(): void
    {
        [$org,$brand,$actor,$job] = $this->fixture();
        $repo = app(PublicationExecutionRepositoryContract::class);
        $repo->arm($org, $brand, $job, $actor->id, 0);
        $repo->claim($job->id, 'lease-2');
        $key = $repo->startAttempt($job->id, 'lease-2');
        $this->assertTrue($repo->finishAttempt($job->id, 'lease-2', $key, new DispatchOutcome('UNCERTAIN', 'TRANSPORT_AMBIGUOUS')));
        $this->assertSame('UNCERTAIN', $job->fresh()->state);
        $this->assertNotContains($job->id, $repo->dueIds(10));
        $this->expectException(ConflictHttpException::class);
        $repo->arm($org, $brand, $job->fresh(), $actor->id, $job->fresh()->version);
    }

    public function test_blocked_before_send_returns_to_manual_hold_without_attempt(): void
    {
        [$org,$brand,$actor,$job] = $this->fixture();
        $repo = app(PublicationExecutionRepositoryContract::class);
        $repo->arm($org, $brand, $job, $actor->id, 0);
        $repo->claim($job->id, 'lease-3');
        $this->assertTrue($repo->holdBlocked($job->id, 'lease-3', 'PERMISSION_REVOKED'));
        $this->assertSame('AWAITING_USER',$job->fresh()->state);
        $this->assertDatabaseCount('publication_attempts',0);
        $this->assertNotContains($job->id,$repo->dueIds(10));
    }
}
