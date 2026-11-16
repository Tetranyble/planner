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

/** Full API permission and source migration test: requires live Laravel/MySQL/Kinship. */
final class RecurringCampaignEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_access_is_blocked_and_cross_tenant_target_is_not_exposed(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $base = "/api/v1/organisations/{$org->uuid}/brands/{$brand->uuid}/content/recurring-campaigns";
        $this->getJson($base)->assertUnauthorized();
        $this->postJson($base, [])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($base)->assertForbidden();
    }

    public function test_approved_occurrence_can_be_held_only_after_server_preview_and_manual_review(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => 'Recurring Fixture'])->assertCreated();
        $org = Organisation::query()->where('name', 'Recurring Fixture')->firstOrFail();
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/brands", ['name' => 'Recurring Brand'])->assertCreated();
        $brand = Brand::query()->where('organisation_id', $org->id)->firstOrFail();
        $item = ContentItem::factory()->forBrand($brand)->create(['created_by_user_id' => $owner->id]);
        $version = ContentVersion::factory()->forItem($item)->create(['created_by_user_id' => $owner->id]);
        $connection = ProviderConnection::factory()->forOrganisation($org)->create(['provider' => 'x', 'status' => 'ACTIVE']);
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create(['status' => 'ACTIVE']);
        $target = ContentTarget::factory()->forVersionAndAccount($version, $account)->create(['created_by_user_id' => $owner->id]);
        BrandApprovalPolicy::factory()->forBrand($brand)->create(['state' => 'ACTIVE', 'forbid_self_approval' => true]);
        $base = "/api/v1/organisations/{$org->uuid}/brands/{$brand->uuid}/content";
        $this->actingAs($owner)->postJson("{$base}/{$item->uuid}/targets/{$target->uuid}/approvals", [
            'expected_target_hash' => $target->payload_hash,
        ])->assertCreated();
        $approval = ApprovalRequest::query()->where('brand_id', $brand->id)->firstOrFail();
        $manager = User::factory()->create();
        $role = Role::query()->forWorkspace($org->id)->where('name', 'manager')->firstOrFail();
        app(CurrentOrganisation::class)->within($owner, $org->id, function () use ($org, $manager, $role): void {
            app(OrganisationMembershipService::class)->grant($org, $manager, $role);
        });
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/brands/{$brand->uuid}/members", [
            'user_id' => $manager->id, 'role' => 'manager',
        ])->assertCreated();
        $this->actingAs($manager)->postJson("{$base}/{$item->uuid}/approvals/{$approval->id}/decisions", [
            'decision' => 'APPROVE', 'comment' => 'I independently reviewed this content.',
        ])->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $start = now('Africa/Lagos')->addDays(2)->startOfHour()->format('Y-m-d H:i:s');
        $create = $this->actingAs($owner)->postJson("{$base}/recurring-campaigns", [
            'target_uuid' => $target->uuid, 'rrule' => 'FREQ=DAILY;INTERVAL=1',
            'timezone' => 'Africa/Lagos', 'start_local' => $start, 'max_occurrences' => 3,
        ])->assertCreated()->assertJsonPath('data.state', 'ACTIVE');
        $id = $create->json('data.id');
        $preview = $this->actingAs($owner)->getJson("{$base}/recurring-campaigns/{$id}/preview?".http_build_query([
            'from' => now()->utc()->toIso8601String(), 'to' => now()->addDays(7)->utc()->toIso8601String(),
        ]))->assertOk();
        $slot = $preview->json('data.slots.0.run_at');
        $this->assertNotEmpty($slot);
        $this->actingAs($owner)->postJson("{$base}/recurring-campaigns/{$id}/hold", [
            'run_at' => '2026-12-01 00:00:00',
            'approval_request_id' => $approval->id, 'expected_version' => 0,
        ])->assertStatus(422); // requires exact canonical Z slot format
        $payload = ['run_at' => $slot, 'approval_request_id' => $approval->id, 'expected_version' => 0];
        $this->actingAs($owner)->postJson("{$base}/recurring-campaigns/{$id}/hold", $payload)
            ->assertCreated()->assertJsonPath('data.publication_job.state', 'AWAITING_USER');
        $this->actingAs($owner)->postJson("{$base}/recurring-campaigns/{$id}/hold", $payload)->assertCreated();
        $this->assertDatabaseCount('publication_jobs', 1);
        $this->actingAs($owner)->postJson("{$base}/recurring-campaigns/{$id}/state", [
            'state' => 'PAUSED', 'expected_version' => 0,
        ])->assertOk()->assertJsonPath('data.version', 1);
        $this->actingAs($owner)->postJson("{$base}/recurring-campaigns/{$id}/hold", [
            ...$payload, 'expected_version' => 1,
        ])->assertStatus(409);
        $this->assertDatabaseCount('publication_jobs', 1);
    }
}
