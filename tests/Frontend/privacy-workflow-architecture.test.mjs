import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = p => readFileSync(new URL(`../../${p}`, import.meta.url), 'utf8');

test('Privacy officer API and web routes require verified Kinship owner authorization', () => {
  for (const file of ['routes/api.php','routes/web.php']) {
    const source=read(file);
    assert.match(source,/kinship\.role:owner/);
    assert.match(source,/kinship\.permission:privacy-deletion\.review/);
    assert.match(source,/organisation\.context/);
  }
});

test('Non-CRUD decisions are invokable, not crowded onto resource controllers', () => {
  for (const file of [
    'app/Http/Controllers/Workspace/ResolveMetaDeletionTargetController.php',
    'app/Http/Controllers/Api/V1/ResolveMetaDeletionTargetController.php',
  ]) {
    const source=read(file);
    assert.match(source,/function __invoke\(/);
    assert.doesNotMatch(source,/function (?:add|approve|resolve|accept|review)\(/);
  }
});

test('Review UI has evidence and retention fields with explicit approval action', () => {
  const source=read('resources/js/Pages/Organisations/Privacy/DeletionReviews.jsx');
  assert.match(source,/evidence_reference/);
  assert.match(source,/legal_basis/);
  assert.match(source,/review_again_at/);
  assert.match(source,/Record decision/);
});

test('Provider grants remain separated from their account assignments', () => {
  const group=read('database/migrations/2026_11_14_180001_create_provider_grant_groups.php');
  const link=read('database/migrations/2026_11_14_180002_create_provider_grant_links.php');
  assert.match(group,/provider_grant_groups/);
  assert.match(link,/provider_grant_links/);
  assert.match(link,/provider_connection_id/);
  assert.match(group,/actor_digest/);
});

test('Published privacy status describes legal retention distinctly from deletion', () => {
  const source=read('resources/views/privacy/meta-deletion-status.blade.php');
  assert.match(source,/COMPLETED_WITH_RETENTION/);
  assert.match(source,/documented legal obligation/);
});
