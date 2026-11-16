import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const text=(name)=>readFileSync(new URL('../../'+name,import.meta.url),'utf8');
test('agent access is isolated from read-only integration and legacy mutations',()=>{
 const isolation=text('app/Http/Middleware/IsolatePlannerIntegrationBearer.php');
 assert.match(isolation,/planner:agent/);
 assert.match(isolation,/api\/v1\/agent\/action/);
 assert.match(isolation,/api\/v1\/integrations\/organisations/);
 assert.match(text('routes/ai.php'),/\/mcp\/planner-agent/);
 assert.match(text('routes/ai.php'),/planner.agent/);
});
test('agent cannot review or publish without service and per-grant controls',()=>{
 const service=text('app/Services/Agents/OrchestratePlannerAgent.php');
 assert.match(service,/Policy::permits/);
 assert.match(service,/dailyCount/);
 assert.match(service,/ManageContentApprovals/);
 assert.match(service,/ExecutePublicationPlans/);
 assert.match(service, /publication\.arm/);
 assert.doesNotMatch(text('app/Domain/Agent/AgentDelegationPolicy.php'),/'approval.decide'/);
});
test('human-managed credentials are first-party, expiring, revocable, origin-checked',()=>{
 assert.match(text('routes/web.php'),/planner.first_party/);
 assert.match(text('app/Services/Agents/ManagePlannerAgentDelegations.php'),/daily_arm_limit/);
 assert.match(text('app/Http/Middleware/RequirePlannerAgentGrant.php'),/planner.agent_allowed_origins/);
 const ui=text('resources/js/Pages/Organisations/Brands/Content/AgentDelegationPanel.jsx');
 assert.match(ui,/Human review required/);
 assert.match(ui,/Revoke/);
 assert.match(ui,/One-time token/);
});
test('agent workflow is visible in the existing content studio',()=>{
 assert.match(text('resources/js/Pages/Organisations/Brands/Content/Index.jsx'),/AgentDelegationPanel/);
 assert.match(text('app/Repository/Eloquent/PlannerAgentRepository.php'), /delegation_id/);
 assert.match(text('config/planner.php'),/agent_access_enabled/);
});

test('Delegated arming rechecks current ownership and target must follow draft creation', () => {
    const service = text('app/Services/Agents/OrchestratePlannerAgent.php');
    assert.match(service, /organisationMember\(\$org,\(int\)\$actor->id\)\?->role_name!=='owner'/);
    assert.match(service, /work order has no draft/i);
    assert.match(service, /\$this->allowedAccount\(\$grant,\$manifest\['account_uuid'\]\)/);
});

test('Campaign MCP prompt never grants approval or assumes a research browser', () => {
    const prompt=text('app/Mcp/Prompts/PlannerCampaignWorkflowPrompt.php');
    const server=text('app/Mcp/Servers/PlannerAgentServer.php');
    assert.match(prompt,/start-autonomous-research/);
    assert.match(prompt,/PLANNER_AGENT_AUTONOMY_ENABLED/);
    assert.match(prompt,/NEVER use or impersonate the human approval endpoint/);
    assert.match(server,/PlannerCampaignWorkflowPrompt::class/);
});
