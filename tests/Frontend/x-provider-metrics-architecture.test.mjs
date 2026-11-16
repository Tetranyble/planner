import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
const src=(p)=>fs.readFileSync(new URL(`../../${p}`,import.meta.url),'utf8');
test('X analytics stays attached to the existing durable queue and permission gates',()=>{
 const worker=src('app/Services/Analytics/ProcessProviderMetricSyncs.php');
 const repo=src('app/Repository/Eloquent/ProviderMetricSyncRepository.php');
 const routes=src('routes/api.php');
 assert.match(worker,/XPostStatsClientContract/);
 assert.match(worker,/XPostStatistics::normalize/);
 assert.match(repo,/x_metric_sync_enabled/);
 assert.match(repo,/hash_equals/);
 assert.match(routes,/kinship\.permission:approval_request\.index/);
});
test('API totals separate X public impressions from YouTube views',()=>{
 const repo=src('app/Repository/Eloquent/ProviderMetricSyncRepository.php');
 const ui=src('resources/js/Pages/Organisations/Brands/Content/SocialMetricsPanel.jsx');
 assert.match(repo,/provider_breakdown/);
 assert.match(repo,/Never combine YouTube views and X impressions/);
 assert.match(ui,/provider_breakdown/);
 assert.match(ui,/No cross-provider totals or ROI/);
});
test('X client only uses fixed official read-only endpoint',()=>{
 const transport=src('app/Domain/Analytics/Provider/LaravelXPostStatsClient.php');
 assert.match(transport,/https:\/\/api\.x\.com\/2\/tweets\//);
 assert.match(transport,/allow_redirects/);
 assert.doesNotMatch(transport, /->post\(|->put\(|->delete\(/);
});
