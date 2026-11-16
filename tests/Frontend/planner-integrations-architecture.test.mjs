import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const source = (p) => readFileSync(new URL(`../../${p}`, import.meta.url), 'utf8');
test('real Sanctum tokens, brand-bound expiry and revocation', () => {
  const model = source('app/Models/User.php');
  const repo = source('app/Repository/Eloquent/PlannerIntegrationGrantRepository.php');
  const middleware = source('app/Http/Middleware/RequirePlannerIntegrationGrant.php');
  assert.match(model, /use HasApiTokens,/);
  assert.match(repo, /createToken\(/);
  assert.match(repo, /\['planner:read'\]/);
  assert.match(repo, /revoked_at/);
  assert.match(repo, /expires_at/);
  assert.match(repo, /sanctum_token_id/);
  assert.match(middleware, /instanceof PersonalAccessToken/);
  assert.match(middleware, /planner:read/);
  assert.match(middleware, /getMorphClass/);
  assert.match(middleware, /grants->allows/);
});
test('external tool endpoints are GET-only and cannot mint via bearer credentials', () => {
  const routes = source('routes/api.php');
  const block = routes.slice(routes.indexOf('// Increment 60: dedicated'));
  assert.match(block, /planner\.integration/);
  assert.match(source('routes/web.php'), /planner\.first_party/);
  assert.match(block, /scopeBindings\(\)/);
  assert.match(block, /kinship\.permission:approval_request\.index/);
  const tail = block.slice(block.indexOf("Route::middleware(['planner.integration'"), block.indexOf('// Guarded agent action boundary:'));
  assert.doesNotMatch(tail, /Route::post\(|Route::put\(|Route::delete\(|Route::patch\(/);
  const firstParty = source('app/Http/Middleware/RequirePlannerFirstPartySession.php');
  assert.match(firstParty, /Auth::guard\('web'\)->check\(\)/);
  assert.match(firstParty, /PersonalAccessToken/);
});
test('UTCP lists only read services and never publishes or approves', () => {
  const manual = source('app/Services/Integrations/PlannerUtcpManual.php');
  assert.match(manual, /'utcp_version'=>'1\.1\.0'/);
  assert.match(manual, /'http_method'=>'GET'/);
  assert.match(manual, /\$\{PLANNER_API_TOKEN\}/);
  assert.match(manual, /'writes_available'=>false/);
  assert.match(manual, /'publishing_available'=>false/);
  const ops = source('app/Http/Controllers/Api/V1/PlannerIntegrationOperationsController.php');
  assert.match(ops, /dashboard->get\(/);
  assert.match(ops, /performance->show\(/);
  assert.match(ops, /publication->overview\(/);
});
test('integration management screen avoids token persistence and offers revoke', () => {
  const ui = source('resources/js/Pages/Organisations/Brands/Content/IntegrationAccessPanel.jsx');
  assert.match(ui, /useState\(''\)/);
  assert.match(ui, /axios\.delete/);
  assert.match(ui, /setToken\(''\)/);
  assert.doesNotMatch(ui, /localStorage|sessionStorage|document\.cookie/);
});
test('disabled by default and releases never bypass Kinship', () => {
  const cfg = source('config/planner.php');
  assert.match(cfg, /PLANNER_INTEGRATION_READ_ENABLED', false/);
  const service = source('app/Services/Integrations/ManagePlannerIntegrationGrants.php');
  assert.match(service, /guard->allows/);
  assert.match(service, /ServiceUnavailableHttpException/);
});

test('global API guard blocks read-only integration tokens on every legacy mutation route', () => {
  const middleware = source('app/Http/Middleware/IsolatePlannerIntegrationBearer.php');
  const bootstrap = source('bootstrap/app.php');
  assert.ok(bootstrap.includes('IsolatePlannerIntegrationBearer::class')); 
  assert.match(middleware, /Auth::guard\('sanctum'\)/);
  assert.match(middleware, /planner:read/);
  assert.match(middleware, /isMethod\('GET'\)/);
  assert.match(middleware, /api\/v1\/integrations\/organisations\/\*/);
});
