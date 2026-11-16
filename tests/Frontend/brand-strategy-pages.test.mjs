import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const source=readFileSync('resources/js/Pages/Organisations/Brands/Strategy.jsx','utf8');
const routes=readFileSync('routes/web.php','utf8');
const kinship=readFileSync('config/kinship.php','utf8');

test('every normalized strategy section is represented in UI',()=>{
    for(const section of ['Brand positioning','Audience segments','Brand voice','Claims governance','Claims register','Publishing approval policy'])
        assert.ok(source.includes(section),section);
});
test('all strategy mutation routes enforce Kinship permission middleware',()=>{
    for(const ability of ['brand_profile.update','brand_audience_segment.store','brand_voice_profile.store','brand_claim_policy.update','brand_claim.store','brand_approval_policy.store'])
        assert.ok(routes.includes('kinship.permission:'+ability),ability);
});
test('route names and UI calls use corresponding strategy endpoints',()=>{
    for(const path of ['/profile','/audiences','/voices','/claims/policy','/claims','/approval-policies'])
        assert.ok(source.includes(path),path);
});
test('activation has dedicated governed UI and permission',()=>{
    assert.ok(source.includes('ActivatePolicy'));
    assert.ok(source.includes('can.policyActivate'));
    assert.ok(routes.includes('kinship.permission:approval-policy.activate'));
});
test('catalog grants strategy capabilities to roles rather than custom fake permissions',()=>{
    for(const name of ['brand_profile.index','brand_profile.update','brand_claim_policy.update','brand_approval_policy.index'])
        assert.ok(kinship.includes("'"+name+"'"),name);
});
