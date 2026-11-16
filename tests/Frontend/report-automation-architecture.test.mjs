import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';
const read = file => readFileSync(new URL(`../../${file}`,import.meta.url),'utf8');
test('report automation has tenant-scoped version and run repositories',()=>{
 const source=read('app/Repository/Eloquent/ReportAutomationRepository.php');
 assert.match(source,/where\('organisation_id'/);assert.match(source,/where\('brand_id'/);
 assert.match(source,/METRICS_REPORT_ONLY/);
});
test('manual report runs are idempotent and bounded by brand lock and daily cap',()=>{
 const source=read('app/Services/Automation/ManageReportAutomations.php');
 for (const fragment of ['lockForUpdate','runCountToday','hash','METRICS_REPORT_ONLY','aggregates_complete']) {
  assert.ok(source.includes(fragment),`missing safety marker ${fragment}`);
 }
});
test('API requires Kinship and no automatic dispatch',()=>{
 const source=read('routes/api.php');
 assert.match(source,/report-automations/);assert.match(source,/kinship.permission:approval_request.update/);
 assert.doesNotMatch(read('app/Services/Automation/ManageReportAutomations.php'),/Http::|curl_|publish\(/);
});
test('React desk wires report creation, enable pause, and execution',()=>{
 const source=read('resources/js/Pages/Organisations/Brands/Content/ReportAutomationsPanel.jsx');
 for (const phrase of ['Create report rule','Generate report','Pause','idempotency_key','USER_REPORTED_UNVERIFIED']) {
  assert.ok(source.includes(phrase)|| (phrase==='USER_REPORTED_UNVERIFIED' && source.includes('operator-supplied')),phrase);
 }
});
