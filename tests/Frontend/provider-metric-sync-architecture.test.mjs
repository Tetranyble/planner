import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readFileSync } from 'node:fs';
const get=path=>readFileSync(new URL(`../../${path}`,import.meta.url),'utf8');

test('provider metric collection is opt-in, GET only, scoped, and not a publishing switch',()=>{
    const config=get('config/planner.php');
    const worker=get('app/Services/Analytics/ProcessProviderMetricSyncs.php');
    const client=get('app/Domain/Analytics/Provider/LaravelYouTubeVideoStatsClient.php');
    assert.match(config,/provider_metric_sync_enabled.*false/);
    assert.match(config,/provider_metric_autoqueue_enabled.*false/);
    assert.match(worker,/\$this->client->read\(/);
    assert.match(worker,/where\('organisation_id',\$org->id\)/);
    assert.match(worker,/where\('brand_id',\$brand->id\)/);
    assert.match(worker,/BrandPermissionGuard/);
    assert.match(client,/\->get\('https:\/\/www\.googleapis\.com\/youtube\/v3\/videos'/);
    assert.doesNotMatch(client,/\->post\(|\->put\(|\->delete\(/);
});
test('sync jobs are idempotent, fenced, and never write into a publication status',()=>{
    const repo=get('app/Repository/Eloquent/ProviderMetricSyncRepository.php');
    const migrations=get('database/migrations/2026_11_15_100000_create_provider_metric_sync_runs.php');
    assert.match(repo,/lockForUpdate\(/);
    assert.match(repo,/lease_owner/);
    assert.match(repo,/hash_equals/);
    assert.match(repo,/PROVIDER_API_OBSERVED/);
    assert.match(migrations,/uq_metric_sync_day/);
    assert.match(migrations,/uq_metric_observation_run/);
    assert.doesNotMatch(repo,/->forceFill\(\['state'=>'PUBLISHED'/);
});
test('route and frontend controls enforce scoped Kinship and distinguish evidence types',()=>{
    const routes=get('routes/api.php');
    const metrics=get('resources/js/Pages/Organisations/Brands/Content/SocialMetricsPanel.jsx');
    const sync=get('resources/js/Pages/Organisations/Brands/Content/ProviderMetricSyncPanel.jsx');
    const schedule=get('resources/js/Pages/Organisations/Brands/Content/ProviderMetricSyncSchedulePanel.jsx');
    assert.match(routes,/social-metrics\/provider-syncs/);
    assert.match(routes,/social-metrics\/performance-insights/);
    assert.match(routes,/social-metrics\/provider-sync-schedule/);
    assert.match(routes,/kinship\.permission:approval_request\.update/);
    assert.match(metrics,/PROVIDER_API|provider_api/);
    assert.match(sync,/confirmed YouTube, X, Facebook Page, TikTok, or Instagram publications/);
    assert.match(schedule,/daily sync policy/i);
});
test('comparison requires matching same-video baselines and avoids ROI claims',()=>{
    const repo=get('app/Repository/Eloquent/ProviderMetricSyncRepository.php');
    const signals=get('app/Domain/Analytics/ProviderPerformanceSignals.php');
    assert.match(repo,/matchedTrend/);
    assert.match(repo,/baseline/);
    assert.match(signals,/count\(\$pairs\)<3/);
    assert.match(signals,/SAME_VIDEO_CUMULATIVE_COUNT_COMPARISON/);
    assert.match(signals,/not_measured/);
});
