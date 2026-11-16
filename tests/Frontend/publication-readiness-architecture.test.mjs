import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const source = path => readFileSync(new URL('../../'+path, import.meta.url),'utf8');
test('readiness requires Kinship and a second service-side brand guard',()=>{
  assert.match(source('routes/api.php'), /readiness\.show/);
  assert.match(source('routes/api.php'), /kinship\.permission:approval_request\.index/);
  assert.match(source('app/Services/Content/AssessPublicationReadiness.php'), /guard->allows\(\$actor,\$brand,'approval_request\.index'\)/);
});
test('no scheduling, no publication and no token output',()=>{
  const service=source('app/Services/Content/AssessPublicationReadiness.php');
  assert.match(service,/can_schedule' => false/);
  assert.match(service,/can_publish' => false/);
  assert.match(service,/PUBLISHING_ENGINE_NOT_IMPLEMENTED/);
  assert.doesNotMatch(service, /dispatch\(|Http::post|access_token_ciphertext/);
});
test('readiness repository only accepts fresh automatic publish attestations',()=>{
  const repo=source('app/Repository/Eloquent/PublicationReadinessRepository.php');
  assert.match(repo,/ProviderPublishCapabilityMatrix::capabilityFor/);
  assert.match(repo,/where\('capability', \$capability\)/);
  assert.match(repo,/where\('mode', 'AUTOMATIC'\)/);
  assert.match(repo,/whereNotNull\('valid_until'\)/);
});
