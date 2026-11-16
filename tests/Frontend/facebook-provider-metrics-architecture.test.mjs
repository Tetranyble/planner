import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const source=(file)=>readFileSync(new URL(`../../${file}`,import.meta.url),'utf8');
test('Meta Page insights is separate from post impressions and disabled by default',()=>{
  const config=source('config/planner.php');
  const transport=source('app/Domain/Analytics/Provider/LaravelFacebookPagePostStatsClient.php');
  assert.match(config,/facebook_metric_sync_enabled.*false/);
  assert.match(transport,/post_media_view/);
  assert.match(transport,/https:\/\/graph\.facebook\.com/);
  assert.doesNotMatch(transport,/->post\(|->put\(|->delete\(/);
});
test('Facebook observations reuse fenced tenant-scoped metric queue and account matching',()=>{
  const worker=source('app/Services/Analytics/ProcessProviderMetricSyncs.php');
  const repo=source('app/Repository/Eloquent/ProviderMetricSyncRepository.php');
  assert.match(worker,/MetaPageTokenResolver/);
  assert.match(worker,/pages_read_engagement/);
  assert.match(worker,/FacebookPagePostStatistics::normalize/);
  assert.match(repo,/facebook_metric_sync_enabled/);
  assert.match(repo,/hash_equals/);
  assert.match(repo,/provider_breakdown/);
});
test('Facebook metrics cannot be combined with X or YouTube as a single metric',()=>{
  const repo=source('app/Repository/Eloquent/ProviderMetricSyncRepository.php');
  const ui=source('resources/js/Pages/Organisations/Brands/Content/SocialMetricsPanel.jsx');
  assert.match(repo,/Facebook Page post media views/);
  assert.match(ui,/Facebook Page/);
  assert.match(ui,/No cross-provider totals or ROI/);
});
