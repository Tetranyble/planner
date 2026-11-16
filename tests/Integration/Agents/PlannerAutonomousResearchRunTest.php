<?php

namespace Tests\Integration\Agents;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerAgentAutonomousRun;
use App\Models\User;
use App\Repository\Contracts\PlannerAgentAutonomyRepositoryContract;
use App\Repository\Contracts\PlannerAgentRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

final class PlannerAutonomousResearchRunTest extends TestCase
{
    use RefreshDatabase;

    private function scope(): array
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $grant = app(PlannerAgentRepositoryContract::class)->issue($org, $brand, $actor, [
            'name' => 'Automated editor', 'mode' => 'REVIEW_REQUIRED', 'account_uuids' => [],
            'daily_action_limit' => 20, 'daily_arm_limit' => 0, 'expires_in_days' => 1])['grant'];

        return [$org, $brand, $actor, $grant];
    }

    private function payload(): array
    {
        return ['run_key' => 'campaign-research-run-001', 'title' => 'Consumer preferences',
            'objective' => 'Find reputable sources for editorial strategy', 'resource_kind' => 'SOCIAL_COPY'];
    }

    public function test_stages_are_leased_once_idempotent_and_tenant_scoped(): void
    {
        [$org,$brand,$actor,$grant] = $this->scope();
        $repo = app(PlannerAgentAutonomyRepositoryContract::class);
        $run = $repo->queue($org, $brand, $actor, $grant, $this->payload());
        $this->assertSame($run->id, $repo->queue($org, $brand, $actor, $grant, $this->payload())->id);
        $other = Brand::factory()->forOrganisation($org)->create();
        $this->assertSame([], $repo->list($org, $other));
        $this->assertCount(1, $repo->list($org, $brand));
        $claim = $repo->claim();
        $this->assertSame('RESEARCH', $claim['stage']);
        $this->assertNull($repo->claim());
        $evidence = ['findings' => 'Source-based research with citation.', 'sources' => [
            ['url' => 'https://example.org/report', 'title' => 'Study', 'note' => 'Unverified', 'verified' => false]],
            'research_hash' => str_repeat('a', 64)];
        $this->assertFalse($repo->completeResearch($run->id, 'wrong-lease-token', $evidence));
        $this->assertTrue($repo->completeResearch($run->id, $claim['fence'], $evidence));
        $this->assertFalse($repo->completeResearch($run->id, $claim['fence'], $evidence));
        $second = $repo->claim();
        $this->assertSame('RESOURCE', $second['stage']);
        $this->assertTrue($repo->completeResource($run->id, $second['fence'], [
            'text' => 'A reviewable editorial draft.', 'model' => 'unit-fixture']));
        $this->assertSame('READY_FOR_REVIEW', PlannerAgentAutonomousRun::findOrFail($run->id)->status);
        $this->assertDatabaseHas('planner_agent_autonomous_events', ['run_id' => $run->id, 'event' => 'resource.completed']);
    }

    public function test_human_resource_revision_is_fenced_audited_and_never_rewrites_research(): void
    {
        [$org,$brand,$actor,$grant] = $this->scope();
        $repo = app(PlannerAgentAutonomyRepositoryContract::class);
        $run = $repo->queue($org, $brand, $actor, $grant, $this->payload());
        $first = $repo->claim();
        $repo->completeResearch($run->id, $first['fence'], [
            'findings' => 'Cited, unverified source findings', 'sources' => [['url' => 'https://example.org/study', 'title' => 'Study', 'note' => 'Unverified', 'verified' => false]],
            'research_hash' => str_repeat('c', 64)]);
        $second = $repo->claim();
        $repo->completeResource($run->id, $second['fence'], [
            'text' => 'The first generated marketing resource.', 'model' => 'unit-fixture']);
        $original = PlannerAgentAutonomousRun::findOrFail($run->id);
        $revised = $repo->revise($org, $brand, $grant, $actor, $run->uuid, $original->resource_hash,
            'A human-corrected editorial resource with qualified claims.', 'Removed unverifiable claims');
        $this->assertSame(1, $revised['resource_revision_count']);
        $this->assertTrue($revised['requires_human_approval']);
        $current = PlannerAgentAutonomousRun::findOrFail($run->id);
        $this->assertSame('READY_FOR_REVIEW', $current->status);
        $this->assertSame($original->research_hash, $current->research_hash);
        $this->assertSame(1, (int) $current->resource_revision_count);
        $this->assertDatabaseHas('planner_agent_autonomous_events', ['run_id' => $run->id, 'event' => 'resource.revised']);
        $this->expectException(ConflictHttpException::class);
        $repo->revise($org, $brand, $grant, $actor, $run->uuid, $original->resource_hash,
            'A stale user tried to overwrite the corrected editorial resource.', 'Stale browser content');
    }

    public function test_resource_cannot_be_revised_after_promotion(): void
    {
        [$org,$brand,$actor,$grant] = $this->scope();
        $repo = app(PlannerAgentAutonomyRepositoryContract::class);
        $run = $repo->queue($org, $brand, $actor, $grant, $this->payload());
        PlannerAgentAutonomousRun::whereKey($run->id)->update([
            'status' => 'DRAFT_CREATED', 'resource_text' => 'Already promoted resource text', 'resource_hash' => hash('sha256', 'Already promoted resource text')]);
        $this->expectException(ConflictHttpException::class);
        $repo->revise($org, $brand, $grant, $actor, $run->uuid, hash('sha256', 'Already promoted resource text'),
            'Attempt to edit an already promoted reviewable resource.', 'After draft creation');
    }

    public function test_expired_inflight_work_is_quarantined_never_replayed(): void
    {
        [$org,$brand,$actor,$grant] = $this->scope();
        $repo = app(PlannerAgentAutonomyRepositoryContract::class);
        $run = $repo->queue($org, $brand, $actor, $grant, $this->payload());
        $claim = $repo->claim();
        PlannerAgentAutonomousRun::whereKey($run->id)->update(['lease_expires_at' => now('UTC')->subMinute()]);
        $this->assertSame(1, $repo->quarantineExpired(5));
        $this->assertNull($repo->claim());
        $this->assertSame('UNCERTAIN', PlannerAgentAutonomousRun::findOrFail($run->id)->status);
        $this->assertDatabaseHas('planner_agent_autonomous_events', ['run_id' => $run->id, 'event' => 'execution.uncertain']);
    }

    public function test_cancelled_run_is_never_claimed(): void
    {
        [$org,$brand,$actor,$grant] = $this->scope();
        $repo = app(PlannerAgentAutonomyRepositoryContract::class);
        $run = $repo->queue($org,$brand,$actor,$grant,$this->payload());
        $this->assertTrue($repo->cancel($org,$brand,$run));
        $this->assertNull($repo->claim());
    }
}
