import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read = p => readFileSync(new URL('../../'+p,import.meta.url),'utf8');
test('durable sandbox remains separate from real provider dispatch',()=>{
 const s=read('app/Services/Publishing/RunPublicationSandbox.php');
 assert.match(s,/SimulationHarness::run/); assert.doesNotMatch(s,/Http::|curl_exec|PublicationJob::|ProviderDispatchContract/);
});
test('tenant foreign keys and unique operation keys exist',()=>{
 const s=read('database/migrations/2026_11_14_210000_create_publication_sandbox_runs.php');
 assert.match(s,/fk_sandbox_parent/);assert.match(s,/uq_sandbox_operation/);
});
test('worker uses short fenced leases and quarantines expired claims',()=>{
 const s=read('app/Services/Publishing/RunPublicationSandbox.php');
 assert.match(s,/random_bytes\(16\)/);assert.match(s,/hash_equals/);assert.match(s,/STALE_LEASE_QUARANTINED/);
});
