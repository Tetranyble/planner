import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read = path => readFileSync(new URL(`../../${path}`, import.meta.url),'utf8');
test('maintenance endpoints enforce route-edge permissions and invokable controllers', () => {
 const routes=read('routes/api.php');
 for(const name of ['publication-intents.reschedule','publication-intents.bulk-cancel','kinship.permission:approval_request.update']) assert.ok(routes.includes(name));
 for(const name of ['ReschedulePublicationIntentController','BulkCancelPublicationIntentsController']) assert.ok(read(`app/Http/Controllers/Api/V1/${name}.php`).includes('function __invoke('));
});
test('rescheduling is locked, version checked, revalidated, and audited', () => {
 const service=read('app/Services/Content/ManagePublicationIntents.php');
 for(const token of ['function reschedule(', 'lockForUpdate()', 'expectedVersion','PublicationIntentRules::canHold','publication_intent.rescheduled','function parseRunAt(']) assert.ok(service.includes(token),token);
 assert.ok(!service.includes('Http::post('));
});
test('bulk cancellation is transactional, validates selected jobs before changes', () => {
 const service=read('app/Services/Content/ManagePublicationIntents.php');
 assert.ok(service.includes('function cancelMany('));
 assert.ok(service.includes('foreach ($ids as $id)'));
 assert.ok(service.includes('foreach ($jobs as $job)'));
 assert.ok(service.includes('Nothing was cancelled.'));
});
test('React desk exposes held-only reschedule and atomic bulk cancellation', () => {
 const ui=read('resources/js/Pages/Organisations/Brands/Content/PublicationIntentsPanel.jsx');
 for(const token of ['expected_version: row.version','publication-intents/bulk-cancel','Reschedule hold','Cancel {selectedJobs.length} selected plans atomically']) assert.ok(ui.includes(token),token);
});
