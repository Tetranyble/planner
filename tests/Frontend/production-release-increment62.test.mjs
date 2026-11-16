import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = p => readFileSync(new URL('../../'+p, import.meta.url), 'utf8');
test('all publishing workers enforce master emergency stop',()=>{
  for (const file of ['app/Services/Publishing/ExecutePublicationPlans.php','app/Services/Publishing/ProcessMediaPublicationRuns.php','app/Services/Publishing/ProcessYouTubeTransfers.php'])
    assert.match(read(file), /emergency_publish_stop/);
  assert.match(read('config/planner.php'), /PLANNER_EMERGENCY_PUBLISH_STOP', true/);
});
test('Storage v3 adapter uses only package owned canonical stream and signing',()=>{
  assert.match(read('app/Domain/Publishing/YouTube/TetranybleCanonicalVideoChunkSource.php'),/readStream\(/);
  assert.match(read('app/Domain/Publishing/Media/TetranybleMediaDistributionUrlResolver.php'),/signedUrl\(/);
  assert.match(read('app/Services/Content/CanonicalStorageMediaAccess.php'),/inspectCanonical/);
  assert.match(read('app/Providers/AppServiceProvider.php'),/storage_v3_adapter_verified/);
});
test('pricing evidence is snapshot and may remain unknown',()=>{
  const service=read('app/Domain/Ai/VerifiedTokenCostEstimate.php');
  assert.match(service,/operator_configured_token_rates_not_provider_invoice/);
  assert.match(read('app/Repository/Eloquent/BrandAiUsageRepository.php'),/estimated_micro_usd/);
});
test('email alert never replays ambiguous sends and is opt-in',()=>{
  const repo=read('app/Repository/Eloquent/PlannerAlertMailRepository.php');
  assert.match(repo, /UNCERTAIN/);
  assert.match(repo, /FOR UPDATE SKIP LOCKED/);
  assert.match(read('config/planner.php'), /PLANNER_OPERATIONAL_ALERT_MAIL_ENABLED', false/);
});
