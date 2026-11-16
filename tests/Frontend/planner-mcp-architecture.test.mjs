import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const source = (name) => readFileSync(new URL(`../../${name}`, import.meta.url),'utf8');

test('official MCP package declared without tampering with other dependencies',()=>{
  const composer=JSON.parse(source('composer.json'));
  assert.equal(composer.require['laravel/mcp'],'^1.2');
  assert.equal(composer.require['laravel/sanctum'],'^4.0');
  assert.ok(composer.require['tetranyble/kinship']);
});
test('MCP has independent disabled-by-default release switch',()=>{
  const cfg=source('config/planner.php');
  assert.match(cfg, /PLANNER_MCP_READ_ENABLED', false/);
  assert.match(cfg, /PLANNER_MCP_ALLOWED_ORIGINS', ''/);
  const mcp=source('routes/ai.php');
  assert.match(mcp,/Mcp::web\('\/mcp\/planner'/);
  assert.match(mcp,/auth:sanctum/);
  assert.match(mcp,/planner.mcp/);
  assert.doesNotMatch(mcp,/oauthRoutes|Mcp::local/);
});
test('MCP middleware requires real, exact-scope bearer token and CURRENT membership',()=>{
  const middleware=source('app/Http/Middleware/RequirePlannerMcpGrant.php');
  for(const match of [/bearerToken\(\)/,/instanceof PersonalAccessToken/,/tokenable_type/,/planner:read/,/scopeForToken/,/grants->allows/,/context->select/,/permissions->allows/,/mcp_allowed_origins/,/Cache-Control/])
    assert.match(middleware,match);
  assert.match(middleware,/\$token->abilities === \['planner:read'\]/);
  assert.doesNotMatch(middleware,/createToken|dispatch\(|Http::post/);
});
test('MCP server advertises exactly five explicitly read-only tools',()=>{
  const server=source('app/Mcp/Servers/PlannerReadOnlyServer.php');
  const tools=[...'BrandOperationsTool PublicationCalendarTool OperationalSafetyTool ProviderPerformanceTool PublicationHistoryTool'.split(' ')];
  assert.equal((server.match(/::class,/g)||[]).length,5);
  for(const tool of tools){
    assert.match(server,new RegExp(tool+'::class'));
    const code=source(`app/Mcp/Tools/${tool}.php`);
    assert.match(code,/IsReadOnly/); assert.match(code,/IsIdempotent/);
  }
  const base=source('app/Mcp/Tools/PlannerReadTool.php');
  assert.match(base,/planner_mcp_scope/);
  assert.match(base,/65536/);
  assert.doesNotMatch(base,/http->input\('brand'\)|createToken|->publish\(/);
});
test('MCP and REST reuse same brand-authenticated application services',()=>{
  const reader=source('app/Services/Integrations/PlannerMcpReader.php');
  for(const service of ['BrandOperationsDashboard','PublicationCalendar','PlannerOperationalSafety','ProviderPerformanceDashboard','PublicationOperations'])
    assert.ok(reader.includes(service));
  assert.match(reader,/Unknown read-only tool/);
  assert.doesNotMatch(reader,/DB::table|Http::|->create\(|->update\(/);
});
test('grant source cannot be selected by a client-supplied org or brand ID',()=>{
  const repo=source('app/Repository/Eloquent/PlannerIntegrationGrantRepository.php');
  const body=repo.slice(repo.indexOf('function scopeForToken'),repo.indexOf('public function allows'));
  assert.match(body,/where\('sanctum_token_id'/);
  assert.match(body,/where\('user_id'/);
  assert.match(body,/whereNull\('revoked_at'/);
  assert.match(body,/where\('expires_at', '>', now\(\)\)/);
  assert.match(body,/where\('organisation_id'/);
});
test('production PHP integration and AI-usage evidence are distinct',()=>{
  const model=source('app/Domain/Ai/ProviderTokenEvidence.php');
  assert.match(model,/is_int\(\$value\)/);
  assert.match(model,/return null/);
  const migration=source('database/migrations/2026_11_15_133000_add_provider_token_evidence_to_brand_ai_usage_reservations.php');
  assert.match(migration,/provider_input_tokens/);
  assert.match(migration,/provider_output_tokens/);
  const usage=source('app/Repository/Eloquent/BrandAiUsageRepository.php');
  assert.match(usage,/usage_unreported_count/);
  assert.match(usage,/currency_cost_available'=>false/);
});
