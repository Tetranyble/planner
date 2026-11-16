import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read = p => readFileSync(new URL(`../../${p}`,import.meta.url),'utf8');
test('inspection route uses tenant context and Kinship permission', () => {
 const routes=read('routes/api.php');
 assert.ok(routes.includes("publication-intents/inspect"));
 assert.ok(routes.includes('publication-intents.inspect'));
 assert.ok(routes.includes('kinship.permission:approval_request.index'));
 const controller=read('app/Http/Controllers/Api/V1/InspectHeldPublicationPlansController.php');
 assert.ok(controller.includes('function __invoke('));
 assert.ok(controller.includes("'max:50'"));
});
test('inspection never dispatches, writes jobs, or leaks tenant data', () => {
 const service=read('app/Services/Content/InspectHeldPublicationPlans.php');
 assert.ok(service.includes('findForInspection($org, $brand, $id)'));
 assert.ok(service.includes('publishing_enabled'));
 assert.ok(service.includes('EVIDENCE_UNAVAILABLE'));
 for(const prohibited of ['Http::post(', 'createHeld(', '->cancel(', '->reschedule(', 'state\'=>\'PUBLISHED'])
   assert.ok(!service.includes(prohibited),prohibited);
});
test('operator can inspect held plan blockers and has no dispatch UI', () => {
 const ui=read('resources/js/Pages/Organisations/Brands/Content/PublicationIntentsPanel.jsx');
 assert.ok(ui.includes('Inspect held plans'));
 assert.ok(ui.includes('hard_blockers'));
 assert.ok(ui.includes('Never grants publishing permission'));
});
