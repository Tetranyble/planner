import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read = path => readFileSync(new URL('../../'+path,import.meta.url),'utf8');
test('synthetic request compiler never embeds real provider API endpoints',()=>{
 const text=read('app/Domain/Publishing/Delivery/MockProviderRequestCompiler.php');
 assert.match(text,/\/mock\/v1\//);
 assert.doesNotMatch(text,/graph\.facebook|api\.linkedin|upload\.youtube|open-api\.tiktok|api\.x\.com|Http::|curl_/);
});
test('sandbox processor routes through simulation not real dispatch',()=>{
 const text=read('app/Services/Publishing/RunPublicationSandbox.php');
 assert.match(text,/SimulationHarness::run/);
 assert.doesNotMatch(text,/Http::|curl_exec|ProviderDispatchContract/);
});
test('mock transport has no network-capable client',()=>{
 const text=read('app/Domain/Publishing/Delivery/FixtureMockTransport.php');
 assert.doesNotMatch(text,/Http::|curl_|file_get_contents|Guzzle|\bfetch\(/);
 assert.match(text,/exchangeCount/);
});
test('unknown mock scenarios fail closed',()=>{
 const text=read('app/Domain/Publishing/Delivery/FixtureMockTransport.php');
 assert.match(text,/Unknown mock fixture/);
 assert.match(text,/failed_before_send/);
});
test('operator UI exposes grouped mock transport cases with explicit sandbox caveat',()=>{
 const text=read('resources/js/Pages/Organisations/Brands/Content/PublicationSimulationPanel.jsx');
 assert.match(text,/mock_accepted_no_receipt/);
 assert.match(text,/Offline mock HTTP contract/);
 assert.match(text,/no real provider credentials/);
});
