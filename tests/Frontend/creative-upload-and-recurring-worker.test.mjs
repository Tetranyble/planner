import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = file => readFileSync(new URL(`../../${file}`, import.meta.url), 'utf8');

test('automatic image storage uses the real v3 uploader with private staging and no direct media record', () => {
    const api = read('app/Http/Controllers/Api/V1/DownloadPrivateCreativePreviewController.php');
    const uploader = read('app/Services/Creative/CanonicalCreativeImageUpload.php');
    const ui = read('resources/js/Pages/Organisations/Brands/Content/PrivateCreativePreviewPanel.jsx');
    assert.match(uploader, /UploadMedia/);
    assert.match(uploader, /LaravelIncomingFile::fromUploadedFile/);
    assert.match(uploader, /uploadLibraryFiles\(/);
    assert.match(uploader, /media_asset\.store/);
    assert.match(uploader, /storage_v3_adapter_verified/);
    assert.match(uploader, /creative_auto_storage_enabled/);
    assert.match(uploader, /finally\s*\{\s*@unlink\(\$path\)/);
    assert.doesNotMatch(uploader, /MediaAsset::|Storage::put|->approve\(|->publish\(/);
    assert.match(api, /CanonicalCreativeImageUpload::isReleased\(\)/);
    assert.match(api, /X-Creative-Storage-Ingest-State/);
    assert.match(api, /PENDING_STORAGE_REVIEW/);
    assert.match(ui, /auto_store: autoStore/);
    assert.match(ui, /humanReviewed/);
});

test('recurrence background worker is limited to future projections and cannot create or arm posts', () => {
    const worker = read('app/Services/Scheduling/ExpandRecurringCampaignOccurrences.php');
    const repo = read('app/Repository/Eloquent/RecurringCampaignRepository.php');
    const command = read('app/Console/Commands/ExpandRecurringCampaigns.php');
    const migration = read('database/migrations/2026_11_15_230000_create_recurring_campaign_occurrences.php');
    const routes = read('routes/api.php');
    const consoleRoutes = read('routes/console.php');
    for (const expected of ['recurring_expansion_enabled', 'DB::transaction', 'lockForUpdate()',
        'RecurringSlotCalculator::expand', 'projectSlot(', 'advanceProjection(']) assert.match(worker, new RegExp(expected.replaceAll('(', '\\(').replaceAll(')', '\\)')));
    for (const forbidden of ['ManagePublicationIntents', 'createHeld(', 'Http::post(', '->publish(', '->arm(']) assert.ok(!worker.includes(forbidden), forbidden);
    assert.match(repo, /firstOrCreate/);
    assert.match(repo, /markHeldProjection/);
    assert.match(migration, /uq_recurring_occurrence_key/);
    assert.match(migration, /fk_recurring_occurrence_schedule/);
    assert.match(routes, /recurring-campaigns\/\{scheduleId\}\/occurrences/);
    assert.match(consoleRoutes, /planner:expand-recurring-campaigns/);
    assert.match(command, /--limit=25/);
});

test('production switches remain off by default and projected slots cannot be confused with approval', () => {
    const config = read('config/planner.php');
    const ui = read('resources/js/Pages/Organisations/Brands/Content/RecurringCampaignPanel.jsx');
    const svc = read('app/Services/Scheduling/ManageRecurringCampaigns.php');
    for (const value of ['PLANNER_CREATIVE_AUTO_STORAGE_ENABLED', 'PLANNER_RECURRING_EXPANSION_ENABLED']) {
        assert.match(config, new RegExp(value + "', false"));
    }
    assert.match(ui, /Projected slots/);
    assert.match(ui, /projectionMode/);
    assert.match(svc, /markHeldProjection\(\$schedule, \$slot\)/);
    assert.match(svc, /this->intents->create/);
    assert.match(svc, /dispatch_enabled' => false/);
});
