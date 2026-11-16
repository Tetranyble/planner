import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const source = path => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
test('auto research runs behind independent release switches and owner-created delegation', () => {
 const worker=source('app/Services/Agents/ProcessAutonomousAgentRuns.php');
 const policy=source('config/planner.php');
 assert.match(worker, /agent_autonomy_enabled/);assert.match(worker, /agent_write_enabled/);
 assert.match(worker, /activePrincipal/);assert.match(worker, /completeResearch/);
 assert.match(worker, /completeResource/);assert.match(worker, /quarantine/);
 assert.match(policy, /PLANNER_AGENT_AUTONOMY_ENABLED.*false/);
});
test('durable stage evidence, fencing, tenant scoping and no direct publishing', () => {
 const repo=source('app/Repository/Eloquent/PlannerAgentAutonomyRepository.php');
 const worker=source('app/Services/Agents/ProcessAutonomousAgentRuns.php');
 assert.match(repo,/lock\('for update skip locked'\)/);
 assert.match(repo,/lease_token/);assert.match(repo,/LEASE_EXPIRED/);
 assert.match(repo,/safe_to_retry.*false/);
 assert.match(repo,/where\('organisation_id'/);assert.match(repo,/where\('brand_id'/);
 assert.doesNotMatch(worker,/->publish\(/);assert.doesNotMatch(repo,/published_posts/);
});
test('hosted web research requires actual cited web search, no arbitrary fetch of URLs', () => {
 const gateway=source('app/Services/Agents/SearchWithAttributedSources.php');
 const evidence=source('app/Domain/Agent/AutonomousResearchEvidence.php');
 assert.match(gateway, /'type'=>'web_search'/);assert.match(gateway, /Http::withToken/);
 assert.match(evidence,/url_citation/);assert.match(evidence,/web_search_call/);
 assert.match(evidence,/sources_verified.*false/);
 assert.doesNotMatch(gateway,/->get\(\$url/);
});
test('agent can promote only complete hashed research into human-reviewable draft', () => {
 const agent=source('app/Services/Agents/OrchestratePlannerAgent.php');
 assert.match(agent,/research\.autorun/);assert.match(agent,/resource\.promote/);
 assert.match(agent,/READY_FOR_REVIEW/);assert.match(agent,/resource_hash/);
 assert.match(agent,/research_hash/);assert.match(agent,/content\.draft/);
 assert.match(agent,/requires_human_approval.*true/);
 assert.doesNotMatch(agent,/\$this->approvals->approve\(/);
});
test('UI exposes research and operator review without bearer token persistence', () => {
 const ui=source('resources/js/Pages/Organisations/Brands/Content/AgentDelegationPanel.jsx');
 assert.match(ui,/Autonomous research and content resources/);
 assert.match(ui,/Convert to reviewable draft/);
 assert.match(ui,/run\.status === 'READY_FOR_REVIEW'/);
 assert.doesNotMatch(ui,/localStorage/);
 const api=source('routes/web.php');
 assert.match(api,/StartPlannerAutonomousResearchController/);
 assert.match(api,/PromotePlannerAutonomousResourceController/);
 assert.match(api,/kinship\.permission:content_item\.store/);
});
test('external agent only sees work belonging to current delegation', () => {
 const svc=source('app/Services/Agents/OrchestratePlannerAgent.php');
 const repo=source('app/Repository/Eloquent/PlannerAgentRepository.php');
 assert.match(svc,/listOrders\(\$scope\['organisation'\],\$scope\['brand'\],40,\(int\)\$scope\['delegation'\]->id\)/);
 assert.match(repo,/when\(\$delegationId/);
});
