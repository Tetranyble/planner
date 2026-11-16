import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = file => readFileSync(new URL(`../../${file}`, import.meta.url), 'utf8');
test('Content studio routes have Kinship edge permissions for read, create, revision, media and targets', () => {
 for (const file of ['routes/web.php', 'routes/api.php']) {
  const data = read(file);
  for (const permission of ['content_item.index','content_item.view','content_item.store','content_version.store','media_asset.store','content_target.store']) {
   assert.ok(data.includes(`kinship.permission:${permission}`), `${file} missing ${permission}`);
  }
  assert.match(data,/organisation.context/);
  assert.match(data,/whereUuid\('itemUuid'\)/);
 }
});
test('Content operations remain repository based and route controllers remain conventional',()=>{
 const service=read('app/Services/Content/ManageContentStudio.php');
 assert.match(service,/ContentStudioRepositoryContract/);
 assert.match(service,/BrandPermissionGuard/);
 assert.match(service,/expected_version/);
 assert.match(service,/lockForUpdate/);
 assert.doesNotMatch(service,/ContentItem::(?:create|query)/);
 for(const segment of ['Api/V1','Workspace']) {
  for(const controller of ['ContentItemController','ContentVersionController','MediaAssetController','ContentTargetController']) {
   const code=read(`app/Http/Controllers/${segment}/${controller}.php`);
   assert.match(code,/function store\(/);
   assert.doesNotMatch(code,/function (add|append|linkMedia|target|publish)\(/);
  }
 }
});
test('Version and draft target are immutable, draft-only, and use explicit payload schema',()=>{
 assert.match(read('app/Models/ContentVersion.php'),/static::updating/);
 assert.match(read('app/Domain/Content/ContentVersionPayload.php'),/schema_version/);
 assert.match(read('app/Repository/Eloquent/ContentStudioRepository.php'),/mode==='ASSISTED'/);
 assert.match(read('app/Services/Content/ManageContentStudio.php'),/hasVerifiedAutomaticPublishCapability/);
 assert.match(read('app/Services/Content/ManageContentStudio.php'),/ExecutePublicationPlans::isEnabled/);
 assert.doesNotMatch(read('app/Services/Content/ManageContentStudio.php'),/publicationJobs|sendToProvider|postToFacebook/);
});
test('Canonical media stays in Tetranyble Storage and rejects unsafe cross-tenant references',()=>{
 const inspector=read('app/Services/Content/StorageMediaInspector.php');
 assert.match(inspector,/Tetranyble\\Storage/);
 assert.match(inspector,/workspace_id/);
 assert.match(inspector,/uploaded_by/);
 assert.match(inspector,/VirusScanStatus::CLEAN/);
 assert.match(inspector,/quarantined_at/);
 const repo=read('app/Repository/Eloquent/ContentStudioRepository.php');
 assert.match(repo,/where\('organisation_id'/);
 assert.match(repo,/where\('brand_id'/);
});
test('Factory-backed fixtures, demo seeder, and test matrix remain wired',()=>{
 const seeder=read('database/seeders/ContentStudioDevelopmentSeeder.php');
 assert.match(seeder,/environment\(\['local','testing'\]\)/);
 assert.match(seeder,/ContentItem::factory/);
 assert.match(seeder,/ContentVersion::factory/);
 assert.match(read('database/factories/MediaAssetFactory.php'),/forBrand/);
 assert.match(read('database/factories/ContentVersionFactory.php'),/forItem/);
});
