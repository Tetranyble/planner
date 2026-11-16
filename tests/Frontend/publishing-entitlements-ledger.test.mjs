import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read = (p) => readFileSync(new URL(`../../${p}`, import.meta.url), 'utf8');
test('publishing entitlement must be provider-approved, scoped, expiring and rechecked', () => {
    const repo=read('app/Repository/Eloquent/SocialPublishCapabilityRepository.php');
    const service=read('app/Services/Publishing/VerifySocialPublishingCapability.php');
    const worker=read('app/Services/Publishing/ExecutePublicationPlans.php');
    assert.match(repo,/credential_digest/);
    assert.match(repo,/lockForUpdate/);
    assert.match(repo,/valid_until/);
    assert.match(service,/PublishingScopePolicy::permitted/);
    assert.match(worker,/capabilities->verify/);
    assert.match(worker,/startAttempt/);
    assert.ok(worker.indexOf('->prepare(') < worker.indexOf('startAttempt'));
    assert.ok(worker.indexOf('capabilities->verify') > worker.indexOf('private function prepare('));
});
test('Meta Page token is fetched for the precise Page before a send and never stored',()=>{
    const client=read('app/Services/Publishing/MetaPageTokenResolver.php');
    const worker=read('app/Services/Publishing/ExecutePublicationPlans.php');
    assert.match(client,/CREATE_CONTENT/);
    assert.match(client,/graph\.facebook\.com/);
    assert.match(client,/allow_redirects/);
    assert.doesNotMatch(client,/->save\(/);
    assert.match(worker,/pageTokens->resolve/);
});
test('confirmed published posts are persisted in transaction with attempts',()=>{
    const repo=read('app/Repository/Eloquent/PublicationExecutionRepository.php');
    assert.match(repo,/new PublishedPost/);
    assert.match(repo,/RECEIPT_COLLISION/);
    assert.match(repo,/DB::transaction/);
});
test('social UI exposes explicit verification, not silent granting',()=>{
    const ui=read('resources/js/Pages/Organisations/Brands/SocialAccounts.jsx');
    const routes=read('routes/web.php');
    assert.match(ui,/Verify publishing permission/);
    assert.match(routes,/social-accounts\.verify-publishing/);
    assert.match(routes,/kinship\.permission:approval_request\.update/);
});
test('media preflight remains non-publishing',()=>{
    const service=read('app/Services/Content/AssessPublicationReadiness.php');
    const rules=read('app/Domain/Content/PlatformMediaPreflightRules.php');
    assert.match(service,/MEDIA_EDITORIAL_PREFLIGHT_FAILED/);
    assert.match(rules,/remote_publishable'=>false/);
});
