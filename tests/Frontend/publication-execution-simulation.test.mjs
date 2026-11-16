import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const root = new URL('../../',import.meta.url);
const file = p => readFileSync(new URL(p,root),'utf8');
test('simulator API is scoped by Kinship and throttled',()=>{
 const routes=file('routes/api.php');
 assert.match(routes,/publication-delivery\/simulate/);
 assert.match(routes,/kinship\.permission:approval_request\.index/);
 assert.match(routes,/throttle:15,1/);
});
test('simulator never enables live transport or mutable job states',()=>{
 const gate=file('app/Domain/Publishing/Delivery/DispatchSafetyGate.php');
 const harness=file('app/Domain/Publishing/Delivery/SimulationHarness.php');
 assert.match(gate,/NO_LIVE_PROVIDER_ADAPTER_REGISTERED/);
 assert.match(gate,/may_dispatch'\s*=>\s*false/);
 assert.match(harness,/database_writes'\s*=>\s*0/);
 assert.match(harness,/network_requests'\s*=>\s*0/);
});
test('existing operations interface includes offline simulation panel',()=>{
 const page=file('resources/js/Pages/Organisations/Brands/Content/PublicationIntentsPanel.jsx');
 const simulator=file('resources/js/Pages/Organisations/Brands/Content/PublicationSimulationPanel.jsx');
 assert.match(page,/<PublicationSimulationPanel base=\{base\}/);
 assert.match(simulator,/Offline simulation only/);
});
