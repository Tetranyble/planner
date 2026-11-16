<?php

namespace Tests\Feature\Content;

use App\Authorization\CurrentOrganisation;
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
use App\Services\Membership\OrganisationMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tetranyble\Kinship\Models\Role;

/** Real MySQL + Kinship tests (not certified in the build container). */
final class ContentApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function prepare(User $owner, string $name = 'Approval Sandbox'): array
    {
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => $name])->assertCreated();
        $org = Organisation::query()->where('name', $name)->firstOrFail();
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/brands", ['name' => $name.' Brand'])->assertCreated();
        $brand = Brand::query()->where('organisation_id', $org->id)->firstOrFail();
        $item = ContentItem::factory()->forBrand($brand)->create(['created_by_user_id' => $owner->id]);
        $version = ContentVersion::factory()->forItem($item)->create(['created_by_user_id' => $owner->id]);
        $connection = ProviderConnection::factory()->forOrganisation($org)->create(['provider' => 'tiktok', 'status' => 'ACTIVE']);
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create(['status' => 'ACTIVE']);
        $target = ContentTarget::factory()->forVersionAndAccount($version, $account)->create(['created_by_user_id' => $owner->id]);
        $base = "/api/v1/organisations/{$org->uuid}/brands/{$brand->uuid}/content/{$item->uuid}";

        return [$org, $brand, $item, $version, $target, $base];
    }

    private function manager(User $owner, Organisation $org, Brand $brand): User
    {
        $reviewer = User::factory()->create();
        $role = Role::query()->forWorkspace($org->id)->where('name', 'manager')->firstOrFail();
        $this->actingAs($owner);
        app(CurrentOrganisation::class)->within($owner, $org->id, function () use ($org, $reviewer, $role): void {
            app(OrganisationMembershipService::class)->grant($org, $reviewer, $role);
        });
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/brands/{$brand->uuid}/members",
            ['user_id' => $reviewer->id, 'role' => 'manager'])->assertCreated();

        return $reviewer;
    }

    public function test_submission_requires_active_policy_and_pins_target_hash(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$item,$version,$target,$base] = $this->prepare($owner);
        $route = "{$base}/targets/{$target->uuid}/approvals";
        $this->actingAs($owner)->postJson($route, ['expected_target_hash' => $target->payload_hash])->assertStatus(409);
        BrandApprovalPolicy::factory()->forBrand($brand)->create(['state' => 'ACTIVE']);
        $this->actingAs($owner)->postJson($route, ['expected_target_hash' => str_repeat('e', 64)])->assertStatus(409);
        $this->actingAs($owner)->postJson($route, ['expected_target_hash' => $target->payload_hash])->assertCreated()
            ->assertJsonPath('data.status', 'PENDING');
        $this->actingAs($owner)->postJson($route, ['expected_target_hash' => $target->payload_hash])->assertStatus(409);
        $request = ApprovalRequest::query()->where('brand_id', $brand->id)->firstOrFail();
        $this->assertSame($version->content_hash, $request->policy_snapshot['payload']['content_hash']);
        $this->assertSame($target->uuid, $request->policy_snapshot['payload']['target_uuid']);
        $this->assertDatabaseCount('approval_decisions', 0);
    }

    public function test_reviewer_cannot_self_approve_and_separate_manager_can_approve(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$item,$version,$target,$base] = $this->prepare($owner, 'Independent Review');
        BrandApprovalPolicy::factory()->forBrand($brand)->create(['state' => 'ACTIVE', 'forbid_self_approval' => true]);
        $this->actingAs($owner)->postJson("{$base}/targets/{$target->uuid}/approvals", ['expected_target_hash' => $target->payload_hash])->assertCreated();
        $request = ApprovalRequest::query()->where('brand_id', $brand->id)->firstOrFail();
        $reviewUrl = "{$base}/approvals/{$request->id}/decisions";
        $payload = ['decision' => 'APPROVE', 'comment' => 'Checked the immutable draft and its approval policy.'];
        $this->actingAs($owner)->postJson($reviewUrl, $payload)->assertForbidden();
        $manager = $this->manager($owner, $org, $brand);
        $this->actingAs($manager)->postJson($reviewUrl, $payload)->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->actingAs($manager)->postJson($reviewUrl, $payload)->assertStatus(409);
        $this->assertDatabaseHas('approval_decisions', ['approval_request_id' => $request->id, 'approver_user_id' => $manager->id, 'decision' => 'APPROVE']);
    }

    public function test_quorum_requires_two_distinct_authorized_reviewers(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$item,$version,$target,$base] = $this->prepare($owner, 'Quorum Review');
        BrandApprovalPolicy::factory()->forBrand($brand)->create(['state' => 'ACTIVE', 'default_required_approvals' => 2]);
        $this->actingAs($owner)->postJson("{$base}/targets/{$target->uuid}/approvals", ['expected_target_hash' => $target->payload_hash])
            ->assertCreated()->assertJsonPath('data.required_approvals', 2);
        $request = ApprovalRequest::query()->where('brand_id', $brand->id)->firstOrFail();
        $url = "{$base}/approvals/{$request->id}/decisions";
        $one = $this->manager($owner, $org, $brand);
        $two = $this->manager($owner, $org, $brand);
        $input = ['decision' => 'APPROVE', 'comment' => 'Independent review confirmed immutable draft.'];
        $this->actingAs($one)->postJson($url, $input)->assertOk()->assertJsonPath('data.status', 'PENDING');
        $this->actingAs($one)->postJson($url, $input)->assertStatus(409);
        $this->actingAs($two)->postJson($url, $input)->assertOk()->assertJsonPath('data.status', 'APPROVED')
            ->assertJsonPath('data.approvals_received', 2);
    }

    public function test_new_draft_version_withdraws_pending_approval_and_cross_brand_isolated(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$item,$version,$target,$base] = $this->prepare($owner, 'Revision Security');
        BrandApprovalPolicy::factory()->forBrand($brand)->create(['state' => 'ACTIVE']);
        $this->actingAs($owner)->postJson("{$base}/targets/{$target->uuid}/approvals", ['expected_target_hash' => $target->payload_hash])->assertCreated();
        $request = ApprovalRequest::query()->where('brand_id', $brand->id)->firstOrFail();
        $this->actingAs($owner)->postJson("{$base}/versions", ['expected_version' => 1, 'copy_text' => 'Revision invalidates the old pending approval.'])->assertCreated();
        $this->assertDatabaseHas('approval_requests', ['id' => $request->id, 'status' => 'WITHDRAWN']);
        $this->actingAs($owner)->postJson("{$base}/targets/{$target->uuid}/approvals", ['expected_target_hash' => $target->payload_hash])->assertStatus(409);
        $outsider = User::factory()->create();
        [$outsideOrg,$outsideBrand,$outsideItem,$outsideVersion,$outsideTarget,$outsideBase] = $this->prepare($outsider, 'Outside Brand');
        $this->actingAs($outsider)->getJson("{$base}/approvals")->assertForbidden();
        $this->actingAs($outsider)->postJson("{$base}/approvals/{$request->id}/withdraw")->assertForbidden();
        $this->actingAs($owner)->getJson("{$outsideBase}/approvals")->assertForbidden();
    }

    public function test_readiness_is_read_only_and_fail_closed_even_when_human_review_passes(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$item,$version,$target,$base] = $this->prepare($owner, 'Readiness Sandbox');
        BrandApprovalPolicy::factory()->forBrand($brand)->create(['state' => 'ACTIVE']);
        $this->actingAs($owner)->postJson("{$base}/targets/{$target->uuid}/approvals",
            ['expected_target_hash' => $target->payload_hash])->assertCreated();
        $request = ApprovalRequest::query()->where('brand_id', $brand->id)->firstOrFail();
        $manager = $this->manager($owner, $org, $brand);
        $this->actingAs($manager)->postJson("{$base}/approvals/{$request->id}/decisions",
            ['decision' => 'APPROVE', 'comment' => 'Independent review complete.'])->assertOk();

        $url = "{$base}/targets/{$target->uuid}/approvals/{$request->id}/readiness";
        $this->actingAs($owner)->getJson($url)->assertOk()
            ->assertJsonPath('data.can_schedule', false)
            ->assertJsonPath('data.can_publish', false)
            ->assertJsonPath('data.readiness','BLOCKED');
        $this->assertContains('PUBLISHING_ENGINE_NOT_IMPLEMENTED',
            $this->actingAs($owner)->getJson($url)->json('data.blockers'));
        $this->assertDatabaseCount('approval_requests',1);
        $this->assertDatabaseCount('approval_decisions',1);
    }
}
