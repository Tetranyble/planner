import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const file = path => readFileSync(new URL('../../'+path, import.meta.url),'utf8');
test('risk declarations are explicitly tri-state in request and UI',()=>{
  for (const name of ['CreateContentItemRequest','CreateContentVersionRequest']) {
    const request=file(`app/Http/Requests/Content/${name}.php`);
    assert.match(request, /'ai_generated'=>\['nullable','boolean'\]/);
    assert.match(request, /'includes_sensitive_claim'=>\['nullable','boolean'\]/);
  }
  const component=file('resources/js/Pages/Organisations/Brands/Content/RiskDeclarations.jsx');
  assert.match(component, /Not assessed/);
  assert.match(component, /value:'unknown'/);
  assert.match(component, /value:'no'/);
});
test('approvals use conservative unknown rule matching and preserve revision proof',()=>{
  const service=file('app/Services/Content/ManageContentApprovals.php');
  const matcher=file('app/Domain/Content/RiskRuleMatcher.php');
  assert.match(service,/RiskRuleMatcher::matches/);
  assert.match(matcher,/\$versionAi === null/);
  assert.match(matcher,/\$versionSensitive === null/);
  assert.match(file('app/Domain/Content/ApprovalPayloadSnapshot.php'),/risk_declarations/);
  assert.match(file('app/Domain/Content/ContentVersionPayload.php'),/risk_declarations/);
  assert.match(file('app/Models/ContentVersion.php'),/append-only/);
});
test('migration keeps old version declarations nullable, never defaults unknown to false',()=>{
  const migration=file('database/migrations/2026_11_14_231000_add_content_version_risk_declarations.php');
  assert.equal((migration.match(/->nullable\(\)/g)||[]).length,2);
  assert.doesNotMatch(migration,/->default\(false\)/);
});
