import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read=p=>readFileSync(new URL(`../../${p}`,import.meta.url),'utf8');

test('claim-review endpoint is read-only and respects Kinship context',()=>{
 const routes=read('routes/api.php');
 assert.match(routes,/InspectAiSuggestionClaimsController/);
 assert.match(routes,/kinship\.permission:content_item\.index/);
 const controller=read('app/Http/Controllers/Api/V1/InspectAiSuggestionClaimsController.php');
 assert.match(controller,/ReviewAiSuggestionClaims/);
});
test('claim-review service checks immutable suggestion hash and brand permission',()=>{
 const service=read('app/Services/Ai/ReviewAiSuggestionClaims.php');
 assert.match(service,/hash_equals/);
 assert.match(service,/result_hash/);
 assert.match(service,/content_item\.index/);
 assert.match(service,/activeApprovedWording/);
});
test('editorial scanner cannot certify facts or authorize publication',()=>{
 const scanner=read('app/Domain/Ai/EditorialClaimScanner.php');
 assert.match(scanner,/factual_claims_verified.*false/);
 assert.match(scanner,/publishing_authorized.*false/);
 const panel=read('resources/js/Pages/Organisations/Brands/Content/AiSuggestionsPanel.jsx');
 assert.match(panel,/Inspect claim risks/);
});
