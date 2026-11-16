import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
const read=p=>fs.readFileSync(new URL(`../../${p}`,import.meta.url),'utf8');
test('provider evidence is explicitly opted into campaign generation',()=>{
 const controller=read('app/Http/Controllers/Api/V1/AiCampaignProposalController.php');
 const service=read('app/Services/Ai/ManageCampaignProposals.php');
 assert.match(controller,/use_provider_evidence/);
 assert.match(service,/ProviderEvidenceContext::fromSignals/);
 assert.match(service,/matchedTrendsByProvider/);
 assert.match(service,/provider_evidence_hash/);
 assert.match(service,/independently approved campaign proposals/);
});
test('editorial review does not promote provider observations into automatic posting',()=>{
 const context=read('app/Domain/Ai/ProviderEvidenceContext.php');
 const ui=read('resources/js/Pages/Organisations/Brands/Content/AiCampaignProposalsPanel.jsx');
 assert.match(context,/publishing_authorized/);
 assert.match(context,/content_approval_bypassed/);
 assert.match(ui,/use_provider_evidence/);
 assert.match(ui,/Independent review required/);
});
