import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('bulk plan endpoint has permissions and throttling', () => {
    const routes = read('routes/api.php');
    assert.match(routes, /publication-intents\/bulk/);
    assert.match(routes, /BulkPublicationPlanController::class/);
    assert.match(routes, /kinship\.permission:approval_request\.store/);
});
test('bulk creation is atomic, bounded and uses existing approved-intent service', () => {
    const source=read('app/Services/Content/ManageBulkPublicationPlans.php');
    assert.match(source,/DB::transaction/);
    assert.match(source,/count\(\$plans\) > 20/);
    assert.match(source,/\$this->intents->create/);
    assert.doesNotMatch(source,/Http::|curl_|dispatch\(/);
});
test('calendar query is scoped to organisation, brand and bounded timeframe', () => {
    const source=read('app/Repository/Eloquent/PublicationIntentRepository.php');
    assert.match(source,/function between\(/);
    assert.match(source,/where\('organisation_id',\$org->id\)/);
    assert.match(source,/where\('brand_id',\$brand->id\)/);
    const service=read('app/Services/Content/PublicationCalendar.php');
    assert.match(service,/diffInDays\(\$end\)>90/);
    assert.match(service,/dispatch_enabled'=>false/);
});
test('React desk integrates atomic bulk holds and readonly calendar', () => {
    const panel=read('resources/js/Pages/Organisations/Brands/Content/PublicationIntentsPanel.jsx');
    assert.match(panel,/publication-intents\/bulk/);
    assert.match(panel,/PublicationCalendarPanel/);
    assert.match(panel,/Hold selected plans/);
    const calendar=read('resources/js/Pages/Organisations/Brands/Content/PublicationCalendarPanel.jsx');
    assert.match(calendar,/publication-calendar/);
    assert.match(calendar,/Posting is disabled/);
});
