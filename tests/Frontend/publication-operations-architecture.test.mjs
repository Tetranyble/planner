import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read = path => readFileSync(new URL(`../../${path}`,import.meta.url),'utf8');
test('operations routes enforce Kinship and avoid dispatch',()=>{
 const api=read('routes/api.php');
 for(const term of ['publication-operations.index','publication-operations.show','kinship.permission:approval_request.index']) assert.ok(api.includes(term));
 const c=read('app/Http/Controllers/Api/V1/PublicationOperationsController.php');
 assert.ok(c.includes('function index(') && c.includes('function show('));
 assert.ok(!c.includes('Http::'));
});
test('stale lease recovery is manual and never sends requests',()=>{
 const repo=read('app/Repository/Eloquent/PublicationOperationsRepository.php');
 for(const term of ['lockForUpdate()','LEASE_EXPIRED','UNCERTAIN','manual_reconciliation_required','PublicationEvent']) assert.ok(repo.includes(term));
 assert.ok(!repo.includes('Http::post('));
 const command=read('app/Console/Commands/RecoverPublicationLeases.php');
 assert.ok(command.includes('--dry-run') && command.includes('recoverStale'));
 assert.ok(read('routes/console.php').includes('planner:recover-publication-leases'));
});
test('operator UI surfaces manual reconciliation and history',()=>{
 const ui=read('resources/js/Pages/Organisations/Brands/Content/PublicationIntentsPanel.jsx');
 for(const term of ['publication-operations','Manual reconciliation required','History','Live delivery requires explicit deployment release']) assert.ok(ui.includes(term));
});
