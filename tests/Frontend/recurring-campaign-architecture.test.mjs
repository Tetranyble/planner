import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read = path => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('recurrence routes use first-party authenticated permission boundaries', () => {
    const routes = read('routes/api.php');
    assert.ok(routes.includes("prefix('v1/organisations/{organisation:uuid}/brands/{brand:uuid}/content')"));
    for (const path of ['recurring-campaigns.index','recurring-campaigns.store','recurring-campaigns.preview',
        'recurring-campaigns.state','recurring-campaigns.hold']) assert.ok(routes.includes(path), path);
    for (const guard of ['kinship.permission:approval_request.store','kinship.permission:approval_request.index',
        'kinship.permission:approval_request.update', 'kinship.role:owner,manager']) assert.ok(routes.includes(guard), guard);
});

test('recurrence service forbids delegation, dispatch and changed-state retries', () => {
    const source = read('app/Services/Scheduling/ManageRecurringCampaigns.php');
    for (const guard of ['lockForUpdate()', 'expected_version', 'state !== \'ACTIVE\'',
        'previewSchedule(', 'RecurringSlotCalculator::expand(', 'publication_job_id',
        'ManagePublicationIntents', 'approval_request_id', 'dispatch_enabled']) assert.ok(source.includes(guard), guard);
    assert.ok(!source.includes('Http::post('));
    assert.ok(!source.includes('->arm('));
    assert.ok(!source.includes('->publish('));
});

test('recurrence repo keeps brand scope and stable occurrence identity', () => {
    const repository = read('app/Repository/Eloquent/RecurringCampaignRepository.php');
    const calendar = read('app/Domain/Scheduling/RecurringSlotCalculator.php');
    assert.ok(repository.includes("where('organisation_id'"));
    assert.ok(repository.includes("where('brand_id'"));
    assert.ok(repository.includes("lockForUpdate()"));
    for (const token of ['getTransitions', 'uniqueUtc(', 'occurrence_key', 'sha1(', 'skipped_dst'])
        assert.ok(calendar.includes(token), token);
});

test('operator desk only creates held occurrences through user click and verified approval', () => {
    const ui = read('resources/js/Pages/Organisations/Brands/Content/RecurringCampaignPanel.jsx');
    const wrapper = read('resources/js/Pages/Organisations/Brands/Content/PublicationIntentsPanel.jsx');
    for (const token of ['window.confirm(', 'Hold only', 'approval_request_id: approval.id',
        'expected_version: schedule.version', 'onClick={() => hold(schedule, slot)}', 'Publishing has not been armed'])
        assert.ok(ui.includes(token), token);
    assert.ok(wrapper.includes('<RecurringCampaignPanel base={base} approvals={approvals} onHeld={load} />'));
});
