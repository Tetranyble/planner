import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read = file => readFileSync(new URL(`../../${file}`, import.meta.url),'utf8');

test('approval routes are present in API + Inertia with Kinship at edge and invokable withdrawal',()=>{
 for(const file of ['routes/api.php','routes/web.php']) {
  const code=read(file);
  for(const permission of ['approval_request.index','approval_request.store','approval_request.update','content.approve'])
   assert.ok(code.includes(`kinship.permission:${permission}`),`${file} missing ${permission}`);
  assert.match(code,/WithdrawContentApprovalController::class/);
  assert.match(code,/whereUuid\('targetUuid'\)/);
  assert.match(code,/whereNumber\('requestId'\)/);
 }
});

test('approval service performs dual authorization, locks, hashes, immutable policy checks and no publication',()=>{
 const code=read('app/Services/Content/ManageContentApprovals.php');
 for(const pattern of ['BrandPermissionGuard','ContentApprovalRepositoryContract','lockForUpdate',
  'ApprovalPayloadSnapshot::hash','content.approve','verifyMedia','required_approvals','forbid_self_approval'])
  assert.ok(code.includes(pattern),pattern);
 assert.doesNotMatch(code,/publication_jobs|postToProvider|publishToFacebook|Http::post/);
});

test('approval request snapshots and decisions remain immutable with database pending unique guard',()=>{
 assert.match(read('app/Models/ApprovalRequest.php'),/static::updating/);
 assert.match(read('app/Models/ApprovalDecision.php'),/static::deleting/);
 assert.match(read('database/migrations/2026_11_14_230000_pending_content_approval_uniqueness.php'),/uq_approval_one_pending_target/);
 assert.match(read('app/Services/Content/ManageContentStudio.php'),/withdrawPendingForItem/);
});

test('approval UI exposes precise review actions and only draft targets, not publishing',()=>{
 const ui=read('resources/js/Pages/Organisations/Brands/Content/Show.jsx');
 assert.match(ui,/ApprovalSubmit/);
 assert.match(ui,/ApprovalReview/);
 assert.match(ui,/expected_target_hash/);
 assert.match(ui,/version_number/);
 assert.doesNotMatch(ui,/Publish now|Send to Facebook/);
});

test('approval factories are scoped and the DI binding exists',()=>{
 assert.match(read('database/factories/ApprovalRequestFactory.php'),/forTarget/);
 assert.match(read('database/factories/ApprovalDecisionFactory.php'),/forRequest/);
 assert.match(read('app/Providers/AppServiceProvider.php'),/ContentApprovalRepositoryContract/);
});
