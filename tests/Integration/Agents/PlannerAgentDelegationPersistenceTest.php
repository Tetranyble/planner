<?php

namespace Tests\Integration\Agents;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerAgentDelegation;
use App\Models\User;
use App\Repository\Contracts\PlannerAgentRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

final class PlannerAgentDelegationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_tokens_are_independent_scoped_revocable_and_idempotent(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $other = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $repo = app(PlannerAgentRepositoryContract::class);
        $issued = $repo->issue($org, $brand, $actor, ['name' => 'My delegated researcher', 'mode' => 'REVIEW_REQUIRED',
            'account_uuids' => [], 'daily_action_limit' => 20, 'daily_arm_limit' => 0, 'expires_in_days' => 1]);
        $grant = $issued['grant'];
        $this->assertNotEmpty($issued['plain_text_token']);
        $this->assertSame(['planner:agent'], PersonalAccessToken::findOrFail($grant->sanctum_token_id)->abilities);
        $scope = $repo->scopeForToken($grant->sanctum_token_id, $actor);
        $this->assertSame($brand->id, $scope['brand']->id);
        $this->assertNull($repo->scopeForToken($grant->sanctum_token_id, User::factory()->create()));
        $this->assertFalse($repo->revoke($org, $other, $actor, $grant->id));
        $repo->storeReceipt($grant, 'research.capture', 'fixture-idempotency-0001', str_repeat('a', 64), ['work_order_uuid' => 'sample']);
        $this->assertSame(1, $repo->dailyCount($grant));
        $this->assertSame('sample', $repo->receipt($grant, 'fixture-idempotency-0001')->result['work_order_uuid']);
        $this->assertTrue($repo->revoke($org, $brand, $actor, $grant->id));
        $this->assertNull($repo->scopeForToken($grant->sanctum_token_id, $actor));
        $this->assertNull(PersonalAccessToken::find($grant->sanctum_token_id));
        $this->assertNotNull(PlannerAgentDelegation::findOrFail($grant->id)->revoked_at);
    }

    public function test_research_orders_retain_provenance_and_tenant_isolation(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $other = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $repo = app(PlannerAgentRepositoryContract::class);
        $grant = $repo->issue($org, $brand, $actor, ['name' => 'Research recorder', 'mode' => 'REVIEW_REQUIRED',
            'account_uuids' => [], 'daily_action_limit' => 20, 'daily_arm_limit' => 0, 'expires_in_days' => 1])['grant'];
        $order = $repo->createOrder($org, $brand, $actor, $grant, [
            'title' => 'Campaign research', 'objective' => 'Create editorial plan', 'findings' => 'Unverified customer insights',
            'research_hash' => str_repeat('b', 64), 'sources' => [['url' => 'https://example.org', 'verified' => false]],
        ]);
        $this->assertSame('RESEARCH_RECORDED', $repo->order($org, $brand, $order->uuid)->status);
        $this->assertCount(1, $repo->listOrders($org, $brand, 10));
        $this->assertCount(0, $repo->listOrders($org,$other,10));
        $this->assertDatabaseHas('planner_agent_work_events',['work_order_id' => $order->id, 'action' => 'research.capture']);
    }
}
