import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
const get=(p)=>fs.readFileSync(new URL(`../../${p}`, import.meta.url),'utf8');
test('Instagram metric worker is opt-in, owner matched, and scoped to professional media',()=>{
 const worker=get('app/Services/Analytics/ProcessProviderMetricSyncs.php');
 const config=get('config/planner.php');
 assert.match(config,/instagram_metric_sync_enabled.*false/);
 assert.match(worker,/InstagramMediaStatistics::normalize/);
 assert.match(worker,/instagram_manage_insights/);
});
test('Instagram Graph requests are GET-only on a fixed Meta host',()=>{
 const client=get('app/Domain/Analytics/Provider/LaravelInstagramMediaStatsClient.php');
 assert.match(client,/https:\/\/graph\.facebook\.com/);
 assert.match(client,/owner,like_count,comments_count/);
 assert.match(client,/'metric'=>'views'/);
 assert.doesNotMatch(client,/->post\(|->put\(|->delete\(/);
});
test('Instagram media views are separate from other network counts',()=>{
 const repo=get('app/Repository/Eloquent/ProviderMetricSyncRepository.php');
 const ui=get('resources/js/Pages/Organisations/Brands/Content/SocialMetricsPanel.jsx');
 assert.match(repo,/Instagram media views/);
 assert.match(ui,/No cross-provider totals or ROI/);
 assert.match(ui,/Instagram/);
});
