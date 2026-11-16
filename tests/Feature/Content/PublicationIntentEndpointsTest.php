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
use App\Models\PublicationJob;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Membership\OrganisationMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tetranyble\Kinship\Models\Role;

/** Must run in a configured Laravel+Kinship+MySQL environment. */
final class PublicationIntentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_do_not_disclose_held_queue(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $this->getJson("/api/v1/organisations/{$org->uuid}/brands/{$brand->uuid}/content/publication-intents")
            ->assertUnauthorized();
    }

    public function test_approved_exact_target_can_be_held_idempotently_then_cancelled_without_dispatch(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => 'Held Intent Fixture'])->assertCreated();
        $org = Organisation::query()->where('name', 'Held Intent Fixture')->firstOrFail();
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/brands", ['name' => 'Held Intent Brand'])->assertCreated();
        $brand = Brand::query()->where('organisation_id', $org->id)->firstOrFail();
        $item = ContentItem::factory()->forBrand($brand)->create(['created_by_user_id' => $owner->id]);
        $v = ContentVersion::factory()->forItem($item)->create(['created_by_user_id' => $owner->id]);
        $conn = ProviderConnection::factory()->forOrganisation($org)->create(['provider' => 'x', 'status' => 'ACTIVE']);
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $conn)->create(['status' => 'ACTIVE']);
        $target = ContentTarget::factory()->forVersionAndAccount($v, $account)->create(['created_by_user_id' => $owner->id]);
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
            'decision' => 'APPROVE', 'comment' => 'I independently reviewed the version and policy.',
        ])->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $endpoint = "{$base}/{$item->uuid}/targets/{$target->uuid}/approvals/{$approval->id}/publication-intents";
        $payload = ['occurrence_key' => '3d36791c-b481-47b3-b251-a6396b761820', 'run_at' => now()->addHours(3)->toIso8601String()];
        $this->actingAs($owner)->postJson($endpoint, $payload)->assertCreated()
            ->assertJsonPath('data.state', 'AWAITING_USER')->assertJsonPath('data.can_dispatch', false);
        $this->actingAs($owner)->postJson($endpoint, $payload)->assertCreated();
        $this->assertDatabaseCount('publication_jobs', 1);
        $this->actingAs($owner)->getJson("{$base}/publication-intents")->assertOk();
        $id = PublicationJob::query()->firstOrFail()->id;
        $this->actingAs($owner)->postJson("{$base}/publication-intents/{$id}/cancel")
            ->assertOk()->assertJsonPath('data.state', 'CANCELLED');
        $this->assertDatabaseHas('publication_jobs', ['id' => $id, 'attempt_count' => 0, 'state' => 'CANCELLED']);
    }
}
