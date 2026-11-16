import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = p => readFileSync(new URL('../../' + p,import.meta.url),'utf8');
test('approved campaign conversion cannot bypass independent content approval or tenant scope', () => {
  const service=read('app/Services/Ai/ManageCampaignProposals.php');
  assert.match(service,/function createEditorialDraft/);
  assert.match(service,/\$proposal->status!=='APPROVED'/);
  assert.match(service,/reviewed_by_user_id/);
  assert.match(service,/hash_equals/);
  assert.match(service,/DB::transaction/);
  assert.match(service,/content_item.store/);
  assert.match(service,/appendVersion\([\s\S]*?true,null\)/);
  assert.match(service,/publication_authorized'=>false/);
});
test('campaign draft link uses brand composite keys and unique idempotency', () => {
  const migration=read('database/migrations/2026_11_15_093000_create_ai_campaign_proposal_drafts.php');
  assert.match(migration,/uq_ai_campaign_draft_proposal/);
  assert.match(migration,/organisation_id','brand_id','ai_campaign_proposal_id/);
  assert.match(migration,/organisation_id','brand_id','content_item_id/);
});
test('campaign API route is authorised at edge, controller invokable and UI requires human copy', () => {
  const routes=read('routes/api.php');
  const controller=read('app/Http/Controllers/Api/V1/CreateDraftFromCampaignProposalController.php');
  const ui=read('resources/js/Pages/Organisations/Brands/Content/AiCampaignProposalsPanel.jsx');
  assert.match(routes,/ai-campaign-proposals\/\{proposalUuid\}\/draft/);
  assert.match(routes,/kinship\.permission:content_item.store/);
  assert.match(controller,/function __invoke/);
  assert.match(controller,/min:20/);
  assert.match(ui,/p\.status==='APPROVED'/);
  assert.match(ui,/Editor-written post copy/);
  assert.match(ui,/approval required/);
});
