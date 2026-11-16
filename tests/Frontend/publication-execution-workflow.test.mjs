import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = file => readFileSync(new URL(`../../${file}`, import.meta.url),'utf8');

test('operator arm endpoint is explicitly authorized and version checked', () => {
  const routes = read('routes/api.php');
  const handler = read('app/Http/Controllers/Api/V1/ArmPublicationPlanController.php');
  assert.match(routes,/publication-intents\/\{jobId\}\/arm/);
  assert.match(routes,/kinship\.role:owner,manager/);
  assert.match(routes,/kinship\.permission:approval_request\.update/);
  assert.match(handler,/expected_version/);
  assert.match(handler,/acknowledge_live_delivery/);
});
test('durable worker has tenant-context reauthorization and fenced attempt records', () => {
  const worker = read('app/Services/Publishing/ExecutePublicationPlans.php');
  const repo = read('app/Repository/Eloquent/PublicationExecutionRepository.php');
  assert.match(worker,/CurrentOrganisation/);
  assert.match(worker,/attemptIsFenced/);
  assert.match(repo,/lockForUpdate/);
  assert.match(repo,/operation_key/);
  assert.match(repo,/UNCERTAIN/);
  assert.doesNotMatch(worker,/TextDispatchAuthorization\(true,true,true,true,true,true,true,true,true,true\)/);
});
test('live publisher is independently disabled in configuration', () => {
  const config=read('config/planner.php');
  for(const key of ['text_dispatch_enabled','text_dispatch_provider_approved','text_dispatch_released']){
    assert.match(config,new RegExp(`'${key}'.*false`));
  }
  assert.match(read('app/Services/Publishing/ExecutePublicationPlans.php'),/environment\('production'\)/);
  assert.match(read('app/Console/Commands/ProcessPublicationExecution.php'),/ExecutePublicationPlans/);
});
test('automatic targets require entitlement and new approval', () => {
  const editor = read('app/Services/Content/ManageContentStudio.php');
  assert.match(editor,/hasVerifiedAutomaticPublishCapability/);
  assert.match(editor,/requires_fresh_approval/);
  assert.match(read('app/Repository/Eloquent/ContentStudioRepository.php'),/mode==='ASSISTED'/);
});
test('scheduled but unleased posts can be cancelled before a worker claim', () => {
  const service=read('app/Services/Content/ManagePublicationIntents.php');
  assert.match(service,/AWAITING_USER','SCHEDULED/);
  assert.match(service,/attempt_count/);
  assert.match(read('resources/js/Pages/Organisations/Brands/Content/PublicationIntentsPanel.jsx'),/Cancel before send/);
});
test('execution status is shown in the existing React desk', () => {
  const panel=read('resources/js/Pages/Organisations/Brands/Content/PublicationIntentsPanel.jsx');
  assert.match(panel,/publication-intents\/\$\{row.id\}\/arm/);
  assert.match(panel,/operations\?\.execution\?\.enabled/);
  assert.match(panel,/Automatic retries: OFF/);
});
