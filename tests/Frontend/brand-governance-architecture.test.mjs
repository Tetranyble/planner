import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const web=readFileSync('routes/web.php','utf8');
const api=readFileSync('routes/api.php','utf8');
const ui=readFileSync('resources/js/Pages/Organisations/Brands/Strategy.jsx','utf8');
const kinship=readFileSync('config/kinship.php','utf8');
for(const [name,source] of [['web',web],['api',api]]) {
    test(`${name} governance routes apply Kinship permission at the edge`,()=>{
        for(const permission of [
            'brand_knowledge_source.store','brand_claim_evidence.store',
            'knowledge-source.verify','claim-evidence.verify','claim.submit-review',
            'claim.review','approval-policy.activate']) {
            assert.ok(source.includes(`kinship.permission:${permission}`),permission);
        }
    });
}
test('action verbs use CRUD or invokable controller contracts',()=>{
    for(const name of ['ReviewBrandKnowledgeSourceController','ReviewBrandClaimEvidenceController',
        'SubmitBrandClaimForReviewController','ReviewBrandClaimController','ActivateBrandApprovalPolicyController']) {
        for(const dir of ['Workspace','Api/V1']) {
            const src=readFileSync(`app/Http/Controllers/${dir}/${name}.php`,'utf8');
            assert.match(src,/function __invoke\(/,name);
        }
    }
});
test('UI presents evidence, source and policy governance actions',()=>{
    for(const label of ['Knowledge sources','Claim evidence','Submit for independent review','Activate reviewed policy']) {
        assert.ok(ui.includes(label),label);
    }
});
test('special role permissions live in Kinship catalogue',()=>{
    for(const grant of ['claim.review','claim.submit-review','knowledge-source.verify','claim-evidence.verify','approval-policy.activate'])
        assert.ok(kinship.includes(`'${grant}'`),grant);
});
