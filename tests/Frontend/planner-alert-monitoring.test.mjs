import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const source = (name) => readFileSync(new URL(`../../${name}`, import.meta.url),'utf8');
test('alerts are scoped by BOTH org and brand and serialized under brand lock',()=>{
  const repo=source('app/Repository/Eloquent/PlannerOperationalAlertRepository.php');
  assert.match(repo,/lockForUpdate/);
  assert.match(repo,/where\('organisation_id'/);
  assert.match(repo,/where\('brand_id'/);
  assert.match(repo,/PlannerOperationalAlertEvent::query\(\)->create/);
  assert.doesNotMatch(repo,/Http::|->publish\(|->dispatch\(/);
});
test('alert data has composite FK and idempotent signal uniqueness',()=>{
  const migration=source('database/migrations/2026_11_15_134000_create_planner_operational_alerts.php');
  assert.match(migration,/uq_planner_alert_scope_signal/);
  assert.match(migration,/fk_planner_alert_brand/);
  assert.match(migration,/fk_planner_alert_event_scope/);
  assert.match(migration,/dropIfExists\('planner_operational_alert_events'\)/);
});
test('scanning opt-in OFF and UI clearly distinguishes historical states',()=>{
  assert.match(source('config/planner.php'),/PLANNER_OPERATIONAL_ALERT_SCAN_ENABLED', false/);
  const job=source('app/Console/Commands/ScanPlannerOperationalAlerts.php');
  assert.match(job,/operational_alert_scan_enabled/);
  assert.match(job,/--after-id/);
  assert.match(job,/whereHas\('organisation'/);
  const ui=source('resources/js/Pages/Organisations/Brands/Content/OperationalSafetyPanel.jsx');
  assert.match(ui,/historical_alerts/);
  assert.match(ui,/not provider-confirmed delivery/);
});
