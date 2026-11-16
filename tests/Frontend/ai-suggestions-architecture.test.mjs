import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
const read = name => fs.readFileSync(new URL(`../../${name}`, import.meta.url),'utf8');
test('AI review UI is connected to the content studio',()=>{
 const page=read('resources/js/Pages/Organisations/Brands/Content/Index.jsx');
 const component=read('resources/js/Pages/Organisations/Brands/Content/AiSuggestionsPanel.jsx');
 assert.match(page,/AiSuggestionsPanel/);
 assert.match(component,/Convert to draft/);
 assert.match(component,/credentials: 'same-origin'/);
});
test('service enforces brand permission and versioned promotion',()=>{
 const service=read('app/Services/Ai/ManageAiSuggestions.php');
 for(const fragment of ['content_item.store','repo->find($org,$brand,$uuid,true)','hash_equals','studio->create','ai_generated\'=>true']) assert.ok(service.includes(fragment),fragment);
});
test('API keeps Kinship at route edge and rate limits AI',()=>{
 const routes=read('routes/api.php');
 for(const fragment of ['ai-suggestions','kinship.permission:content_item.store','throttle:5,1']) assert.ok(routes.includes(fragment),fragment);
});
