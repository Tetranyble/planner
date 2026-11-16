import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = path => readFileSync(new URL(`../../${path}`, import.meta.url),'utf8');
test('Meta inspection is bounded and does not enable publishing', () => {
    const service = read('app/Repository/Eloquent/MetaCapabilityInspectionRepository.php');
    assert.match(service,/lockForUpdate/);
    assert.match(service,/UNAVAILABLE/);
    assert.match(service,/PUBLISHING_ADAPTER_NOT_APPROVED/);
    assert.match(service,/META_SCOPE_OR_TARGET_MISSING/);
});
test('privacy followups require Kinship at the route edge', () => {
    const routes = read('routes/api.php');
    assert.match(routes,/kinship.permission:privacy-deletion.review/);
    assert.match(routes,/ListPrivacyRetentionReviewsController/);
    assert.match(routes,/AcknowledgePrivacyRetentionReviewController/);
});
test('external calls are not embedded in database repository', () => {
    const repo = read('app/Repository/Eloquent/MetaCapabilityInspectionRepository.php');
    assert.doesNotMatch(repo,/Http::/);
    assert.match(read('app/Social/Providers/MetaAccessTokenInspector.php'),/debug_token/);
});
test('owner screen explains acknowledgement does not erase data', () => {
    const ui = read('resources/js/Pages/Organisations/Privacy/DeletionReviews.jsx');
    assert.match(ui,/does not certify deletion/);
    assert.match(ui,/Acknowledge follow-up/);
});
