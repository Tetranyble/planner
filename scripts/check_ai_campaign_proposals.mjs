import fs from 'node:fs';
import assert from 'node:assert/strict';
const read = p => fs.readFileSync(new URL(`../${p}`, import.meta.url), 'utf8');
const routes = read('routes/api.php');
const service = read('app/Services/Ai/ManageCampaignProposals.php');
const ui = read('resources/js/Pages/Organisations/Brands/Content/AiCampaignProposalsPanel.jsx');
const model = read('app/Models/AiCampaignProposal.php');
const migration = read('database/migrations/2026_11_14_235000_create_ai_campaign_proposals.php');
const assertions = [
    ['route has Kinship store permission', routes.includes("'kinship.permission:content_item.store'")],
    ['route has Kinship review permission', routes.includes("'kinship.permission:approval_request.update'")],
    ['route requires manager or owner to review', routes.includes("'kinship.role:owner,manager','kinship.permission:approval_request.update'")],
    ['service forbids self review', service.includes('requested_by_user_id === (int)$reviewer->id')],
    ['service checks immutable result hash', service.includes('hash_equals(')],
    ['service uses scoped repo', service.includes('$this->repo->find($org,$brand,$uuid,true)')],
    ['service reauthorizes inside transaction', service.includes("$this->authorize($reviewer,$org,$brand,'approval_request.update');")],
    ['migration has tenant scope', migration.includes("'organisation_id','brand_id'")],
    ['model has reviewer metadata', model.includes('reviewed_by_user_id')],
    ['UI provides review actions', ui.includes("review(p,'APPROVED')") && ui.includes("review(p,'REJECTED')")],
    ['UI discloses no automatic posting', ui.includes('Every proposed post still requires its own draft and approval.')],
    ['no publishing calls inside service', !service.includes('publish(') && !service.includes('dispatch(')],
];
for (const [name, pass] of assertions) { assert.ok(pass,name); console.log('PASS',name); }
console.log(`${assertions.length}/${assertions.length} campaign proposal checks passed`);
