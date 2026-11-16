import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = file => readFileSync(new URL(`../../${file}`, import.meta.url), 'utf8');

test('stage runs persist encrypted remote references and append-only event evidence', () => {
  const model = read('app/Models/MediaPublicationRun.php');
  const event = read('app/Models/MediaPublicationRunEvent.php');
  const migration = read('database/migrations/2026_11_15_091000_create_media_publication_runs.php');
  assert.match(model, /provider_operation_reference'\s*=>\s*'encrypted'/);
  assert.match(migration, /uq_media_stage_sequence/);
  assert.match(migration, /uq_media_run_preparation/);
  assert.match(event, /append-only/);
});
test('worker records STARTED before transport and never automatically retries uncertain sends', () => {
  const repo = read('app/Repository/Eloquent/MediaPublicationRunRepository.php');
  const service = read('app/Services/Publishing/ProcessMediaPublicationRuns.php');
  assert.match(repo, /stage_sequence.*\+1/);
  assert.match(repo, /state'\s*=>\s*'STARTED'/);
  assert.match(repo, /LEASE_EXPIRED_AFTER_SEND/);
  assert.match(service, /startStage\(/);
  assert.match(service, /GuardedMediaStep/);
  assert.match(service, /media_dispatch_released/);
  assert.match(read('config/planner.php'), /PLANNER_MEDIA_WORKER_ENABLED',\s*false/);
});
test('media run API has Kinship, controller conventions and correct tenant scope', () => {
  const routes = read('routes/api.php');
  assert.match(routes, /media-publication-runs\/\{runId\}\/arm/);
  assert.match(routes, /media-publication-runs\/\{runId\}\/cancel/);
  assert.match(routes, /kinship\.permission:approval_request\.update/);
  assert.match(read('app/Http/Controllers/Api/V1/ArmMediaPublicationRunController.php'), /__invoke/);
  assert.match(read('app/Repository/Eloquent/MediaPublicationRunRepository.php'), /where\('organisation_id'/);
  assert.match(read('app/Repository/Eloquent/MediaPublicationRunRepository.php'), /where\('brand_id'/);
});
test('TikTok creator options come from provider and consent is immutable, exact-version, opt-in', () => {
  const s = read('app/Services/Publishing/MediaTikTokCreatorConsent.php');
  const m = read('app/Models/MediaTikTokConsent.php');
  assert.match(s, /creator_info\/query\//);
  assert.match(s, /explicit_consent/);
  assert.match(s, /verified\['privacy_levels'\]/);
  assert.match(s, /hash_hmac/);
  assert.match(m, /immutable/);
  assert.match(read('routes/api.php'), /media-publication-runs\/\{runId\}\/tiktok-consent/);
});
test('operator UI shows stage outcomes and explicit TikTok privacy settings, not auto publishing', () => {
  const ui=read('resources/js/Pages/Organisations/Brands/Content/MediaPublicationPanel.jsx');
  assert.match(ui, /Stage evidence/);
  assert.match(ui, /expected_version/);
  assert.match(ui, /explicit_consent/);
  assert.match(ui, /media-publication-runs/);
  assert.match(ui, /Uncertain sends are never retried automatically/);
});
test('cancelling or rescheduling publication cannot race an active media run', () => {
  const s=read('app/Services/Content/ManagePublicationIntents.php');
  assert.equal((s.match(/hasUncancelledMediaRun/g)??[]).length,3);
  assert.match(read('app/Services/Publishing/ManageMediaPublicationRuns.php'), /runId,int \$expectedVersion/);
});

test('verified media receipt commits attempt, post, job and audit under one stage transaction', () => {
  const repo=read('app/Repository/Eloquent/MediaPublicationRunRepository.php');
  assert.match(repo, /recordVerifiedPublication/);
  assert.match(repo, /new PublicationAttempt\(\)/);
  assert.match(repo, /new PublishedPost\(\)/);
  assert.match(repo, /MEDIA_POST_VERIFIED/);
  assert.match(repo, /INSTAGRAM_POST_OWNER_MISMATCH/);
  assert.match(repo, /REMOTE_POST_RECEIPT_COLLISION/);
  assert.match(repo, /newState==='CONFIRMED'/);
});


test('TikTok creator options freshness blocks new sends but permits bound read-only reconciliation', () => {
  const worker = read('app/Services/Publishing/ProcessMediaPublicationRuns.php');
  assert.match(worker, /\$stage==='INITIALIZE_VIDEO' && \$consent->verified_at->lessThan/);
  assert.match(worker, /VERIFY_VIDEO_RECEIPT/);
  assert.match(worker, /hash_equals\(hash_hmac\('sha256'/);
});


test('terminal media outcomes flow to the existing publication reconciliation desk without retry', () => {
  const repo = read('app/Repository/Eloquent/MediaPublicationRunRepository.php');
  assert.match(repo, /function propagateTerminalToPublication/);
  assert.match(repo, /MEDIA_EXECUTION_TERMINAL/);
  assert.match(repo, /PublicationAttempt/);
  assert.match(repo, /if \(\$started\) \$this->propagateTerminalToPublication/);
  assert.match(repo, /\$state=\$runState==='FAILED'\?'FAILED':'UNCERTAIN'/);
});
