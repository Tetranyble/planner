import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = f => readFileSync(new URL('../../'+f,import.meta.url),'utf8');
test('operations dashboard is scoped, read-only, and permission-checked', () => {
  const routes=read('routes/api.php'), service=read('app/Services/Analytics/BrandOperationsDashboard.php');
  const repository=read('app/Repository/Eloquent/BrandOperationsMetricsRepository.php');
  assert.match(routes,/operations-dashboard.*BrandOperationsDashboardController/);
  assert.match(routes,/kinship.permission:approval_request.index/);
  assert.match(service,/guard->allows/);
  assert.match(service,/provider_engagement_available' => false/);
  assert.match(repository,/where\('organisation_id'/);
  assert.match(repository,/where\('brand_id'/);
  assert.doesNotMatch(repository,/DB::table\(/);
});
test('React content studio exposes operational KPIs without inventing engagement', () => {
  assert.match(read('resources/js/Pages/Organisations/Brands/Content/Index.jsx'), /OperationsDashboardPanel/);
  const ui=read('resources/js/Pages/Organisations/Brands/Content/OperationsDashboardPanel.jsx');
  assert.match(ui,/not social reach/);
  assert.match(ui,/AbortController/);
});
