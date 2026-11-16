import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const source = (p) => readFileSync(new URL(`../../${p}`, import.meta.url), 'utf8');
test('operational safety queries are strictly brand-scoped and aggregate-only', () => {
  const repo = source('app/Repository/Eloquent/PlannerOperationalSafetyRepository.php');
  assert.match(repo, /organisation_id/);
  assert.match(repo, /brand_id/);
  assert.match(repo, /whereIn\('state'/);
  assert.match(repo, /->count\(\)/);
  assert.doesNotMatch(repo, /->delete\(|->update\(|->create\(|->post\(/);
});
test('separate API/web read-only routes re-check Kinship; no recovery buttons', () => {
  const api = source('routes/api.php');
  const web = source('routes/web.php');
  const panel = source('resources/js/Pages/Organisations/Brands/Content/OperationalSafetyPanel.jsx');
  assert.match(api, /planner\.integration/);
  assert.match(api, /PlannerOperationalSafetyController/);
  assert.match(web, /operational-safety/);
  assert.match(web, /kinship\.permission:approval_request\.index/);
  assert.match(panel, /axios\.get/);
  assert.doesNotMatch(panel, /axios\.post|axios\.patch|axios\.delete/);
});
