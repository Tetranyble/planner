import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readFileSync } from 'node:fs';
const read=path=>readFileSync(new URL(`../../${path}`,import.meta.url),'utf8');
test('social metric write and read require Kinship edge permissions',()=>{
    const routes=read('routes/api.php');
    assert.match(routes,/get\('social-metrics'.*?SocialMetricSnapshotController/s);
    assert.match(routes,/post\('social-metrics'.*?SocialMetricSnapshotController/s);
    assert.match(routes,/kinship\.permission:approval_request\.update/);
});
test('reporting is branded as unverified and scoped via repository',()=>{
    const repo=read('app/Repository/Eloquent/SocialMetricRepository.php');
    assert.match(repo,/where\('organisation_id',\$org->id\)/);
    assert.match(repo,/where\('brand_id',\$brand->id\)/);
    assert.match(repo,/USER_REPORTED_UNVERIFIED/);
    assert.match(repo,/truncated/);
});
test('database enforces scoped idempotency and immutable evidence shape',()=>{
    const migration=read('database/migrations/2026_11_14_235200_create_social_metric_snapshots.php');
    assert.match(migration,/uq_social_metric_submission/);
    assert.match(migration,/payload_hash/);
    assert.match(migration,/fk_social_metric_brand/);
});
test('content studio renders an evidence-labelled metrics interface',()=>{
    const component=read('resources/js/Pages/Organisations/Brands/Content/SocialMetricsPanel.jsx');
    const index=read('resources/js/Pages/Organisations/Brands/Content/Index.jsx');
    assert.match(component,/Not verified with social networks/);
    assert.match(component,/report\.truncated/);
    assert.match(index,/SocialMetricsPanel/);
});
