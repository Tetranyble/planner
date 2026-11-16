<?php

namespace Tests\Integration\Agents;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\PlannerAgentRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlannerAgentApiIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_token_cannot_call_existing_application_mutation_endpoints(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $user = User::factory()->create();
        $repo = app(PlannerAgentRepositoryContract::class);
        $token = $repo->issue($org, $brand, $user, ['name' => 'Test agent', 'mode' => 'REVIEW_REQUIRED',
            'account_uuids' => [], 'daily_action_limit' => 10, 'daily_arm_limit' => 0, 'expires_in_days' => 1])['plain_text_token'];
        $this->withToken($token)->postJson('/api/v1/organisations/'.$org->uuid.'/brands/'.$brand->uuid.'/content',
            ['title' => 'Unauthorized'])->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/integrations/organisations/'.$org->uuid.'/brands/'.$brand->uuid.'/operations')
            ->assertForbidden();
    }

    public function test_read_only_token_cannot_call_agent_action_boundary(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('read-only', ['planner:read'], now()->addDay());
        $this->withToken($token->plainTextToken)->postJson('/api/v1/agent/action', [
            'action' => 'research.capture', 'idempotency_key' => 'review-agent-unit0001', 'payload' => [],
        ])->assertForbidden();
    }
}
