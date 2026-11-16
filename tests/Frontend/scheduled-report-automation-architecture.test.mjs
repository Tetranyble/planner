import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';
const source = f => readFileSync(new URL(`../../${f}`,import.meta.url),'utf8');
test('hourly cron stays behind default-off independent scheduler kill switch',()=>{
 assert.match(source('config/planner.php'),/report_scheduler_enabled.*false/);
 assert.match(source('app/Console/Commands/ProcessScheduledReportAutomations.php'),/report_scheduler_enabled/);
 assert.match(source('routes/console.php'),/process-scheduled-reports/);
});
test('scheduler idempotency and row locking, and report-only actions',()=>{
 const svc=source('app/Services/Automation/ManageReportAutomations.php');
 for(const needle of ['lockForUpdate','daily-report:','runCountToday','METRICS_REPORT_ONLY','startOfDay','dryRun']) assert.ok(svc.includes(needle),needle);
 assert.doesNotMatch(svc,/Http::|curl_|publish\(/);
});
test('schedule is explicit in dashboard and existing manual run remains',()=>{
 const panel=source('resources/js/Pages/Organisations/Brands/Content/ReportAutomationsPanel.jsx');
 for(const needle of ['DAILY_UTC','lookback_days','hour_utc','MANUAL','Generate report']) assert.ok(panel.includes(needle),needle);
});
