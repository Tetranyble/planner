import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = path => readFileSync(new URL(`../../${path}`, import.meta.url),'utf8');
test('reconciliation is tenant-scoped, independent, and never remotely replayed',()=>{
    const repository=read('app/Repository/Eloquent/PublicationReconciliationRepository.php');
    assert.match(repository,/lockForUpdate/);
    assert.match(repository,/submitted_by/);
    assert.match(repository,/canReview/);
    assert.match(repository,/no_automatic_replay/);
    assert.doesNotMatch(repository,/Http::|curl_exec|->post\(/);
});
test('route edge and secondary service authorization exist',()=>{
    const routes=read('routes/api.php');
    const service=read('app/Services/Publishing/ReconcilePublications.php');
    assert.match(routes,/publication-reconciliation\.decide/);
    assert.match(routes,/kinship\.permission:content\.approve/);
    assert.match(service,/guard->allows/);
});
test('preview is offline and always fail closed',()=>{
    const plan=read('app/Domain/Publishing/PublicationDeliveryPlan.php');
    assert.match(plan,/network_requests.*0/);
    assert.match(plan,/may_dispatch.*false/);
});
