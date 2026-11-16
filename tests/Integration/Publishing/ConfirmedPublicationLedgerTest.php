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
use App\Models\PublicationJob;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repository\Contracts\PublicationExecutionRepositoryContract;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Requires disposable MySQL; validates atomic receipt ledger and scoped uniqueness. */
final class ConfirmedPublicationLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function scope(): array
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $version = ContentVersion::factory()->forItem($item)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider('x')->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)
            ->state(['provider' => 'x', 'status' => 'ACTIVE', 'external_account_id' => '1234567'])->create();
        $target = ContentTarget::factory()->forVersionAndAccount($version, $account)->create();
        $policy = BrandApprovalPolicy::factory()->forBrand($brand)->create();
        $approval = ApprovalRequest::factory()->forTarget($target, $policy, $actor)->create();

        return [$org, $brand, $actor, $target, $approval];
    }

    private function completePublicationWithReceipt($org, $brand, $actor, $target, $approval, string $receipt): PublicationJob
    {
        $job = app(PublicationIntentRepositoryContract::class)->createHeld($org, $brand, $target, $approval,
            (string) Str::uuid(), now()->subMinutes(1)->format('Y-m-d H:i:s'));
        $repo = app(PublicationExecutionRepositoryContract::class);
        $repo->arm($org, $brand, $job, $actor->id, 0);
        $owner = (string) Str::uuid();
        $repo->claim($job->id, $owner);
        $key = $repo->startAttempt($job->id, $owner);
        $this->assertTrue($repo->finishAttempt($job->id, $owner, $key,
            new DispatchOutcome('SUCCESS', 'PROVIDER_RECEIPT', 201, null, $receipt)));

        return $job;
    }

    public function test_confirmed_receipt_creates_exactly_one_published_record(): void
    {
        [$org,$brand,$actor,$target,$approval] = $this->scope();
        $job = $this->completePublicationWithReceipt($org, $brand, $actor, $target, $approval, 'post_123456');
        $this->assertSame('PUBLISHED', $job->fresh()->state);
        $this->assertDatabaseHas('published_posts', ['organisation_id' => $org->id, 'brand_id' => $brand->id,
            'publication_job_id' => $job->id, 'external_post_id' => 'post_123456']);
        $this->assertDatabaseCount('published_posts', 1);
    }

    public function test_same_remote_receipt_across_two_jobs_is_quarantined(): void
    {
        [$org,$brand,$actor,$target,$approval] = $this->scope();
        $first = $this->completePublicationWithReceipt($org, $brand, $actor, $target, $approval, 'post_123456');
        $other = $this->completePublicationWithReceipt($org, $brand, $actor, $target, $approval, 'post_123456');
        $this->assertSame('PUBLISHED', $first->fresh()->state);
        $this->assertSame('UNCERTAIN', $other->fresh()->state);
        $this->assertDatabaseCount('published_posts', 1);
    }
}
