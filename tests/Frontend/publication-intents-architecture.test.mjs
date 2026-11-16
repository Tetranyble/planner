import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read = name => readFileSync(new URL(`../../${name}`, import.meta.url),'utf8');
test('held plan API has scoped Kinship routes and separate cancel controller',()=>{
 const api=read('routes/api.php');
 for(const value of ['publication-intents.index','publication-intents.store','publication-intents.cancel','kinship.permission:approval_request.index','kinship.permission:approval_request.store','kinship.permission:approval_request.update']) assert.ok(api.includes(value),value);
 const controller=read('app/Http/Controllers/Api/V1/CancelPublicationIntentController.php'); assert.ok(controller.includes('function __invoke('));
});
test('no dispatch route and job is held rather than scheduled',()=>{
 const repo=read('app/Repository/Eloquent/PublicationIntentRepository.php');
 assert.ok(repo.includes("'state'=>'AWAITING_USER'"));
 assert.ok(repo.includes("'max_attempts'=>0"));
 const service=read('app/Services/Content/ManagePublicationIntents.php');
 assert.ok(service.includes('PublicationIntentRules::canHold'));
 assert.ok(service.includes('lockForUpdate()'));
 assert.ok(!service.includes('Http::post('));
});
test('planning UI acknowledges manual hold and has cancel action',()=>{
 const ui=read('resources/js/Pages/Organisations/Brands/Content/PublicationIntentsPanel.jsx');
 assert.ok(ui.includes('Hold plan'));assert.ok(ui.includes('Cancel'));assert.ok(ui.includes('Publishing is off by default'));
});
