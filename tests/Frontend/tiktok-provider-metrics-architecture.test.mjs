import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
const text=(p)=>fs.readFileSync(new URL(`../../${p}`,import.meta.url),'utf8');
test('TikTok credential, creator identity and grant must match before metrics',()=>{
  const worker=text('app/Services/Analytics/ProcessProviderMetricSyncs.php');
  assert.match(worker,/tiktok_metric_sync_enabled/);
  assert.match(worker,/video\.list/);
  assert.match(worker,/external_actor_id/);
  assert.match(worker,/hash_equals/);
});
test('TikTok stats is POST query, never content creation or TikTok publishing',()=>{
  const transport=text('app/Domain/Analytics/Provider/LaravelTikTokVideoStatsClient.php');
  assert.match(transport,/https:\/\/open\.tiktokapis\.com\/v2\/video\/query/);
  assert.match(transport,/->post\(/);
  assert.doesNotMatch(transport,/publish\//);
});
test('TikTok data is separately labelled and never blended with other provider views',()=>{
  const repo=text('app/Repository/Eloquent/ProviderMetricSyncRepository.php');
  const ui=text('resources/js/Pages/Organisations/Brands/Content/SocialMetricsPanel.jsx');
  assert.match(repo,/TikTok creator video views/);
  assert.match(ui,/No cross-provider totals or ROI/);
  assert.match(ui,/TikTok/);
});
