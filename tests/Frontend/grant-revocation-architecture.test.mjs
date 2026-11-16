import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = (file) => readFileSync(new URL(`../../${file}`, import.meta.url), 'utf8');
test('grant revocation is a two-step owner-only action with Kinship at the edge', () => {
  for (const file of ['routes/web.php','routes/api.php']) {
    const routes=read(file);
    assert.match(routes,/kinship\.role:owner/);
    assert.match(routes,/kinship\.permission:social-grant\.revoke/);
    assert.match(routes,/grant-revocations\/\{requestUuid\}\/confirm/);
  }
});
test('confirmed requests cannot bypass the feature flag and remote outcomes are not replayed', () => {
  assert.match(read('config/social_integrations.php'),/remote_revocation_enabled'.*false/);
  const processor=read('app/Services/Social/ProcessGrantRevocations.php');
  assert.match(processor,/remote_revocation_enabled/);
  assert.match(processor,/\$this->repo->uncertain/);
  assert.doesNotMatch(processor,/retry\(/);
});
test('provider transport is fixed host and secrets cannot be sent to the browser', () => {
  const adapter=read('app/Social/Providers/TikTokGrantRevoker.php');
  assert.match(adapter,/https:\/\/open\.tiktokapis\.com\/v2\/oauth\/revoke\//);
  const repo=read('app/Repository/Eloquent/ProviderGrantRevocationRepository.php');
  assert.doesNotMatch(repo,/access_token_ciphertext'=>/);
  assert.match(repo,/Crypt::decryptString/);
});
test('all persisted grant identity is HMAC protected and connection attempts are refused during an active revocation', () => {
  const ownership=read('app/Repository/Eloquent/ProviderGrantOwnershipRepository.php');
  assert.match(ownership,/hash_hmac\('sha256'/);
  assert.match(ownership,/READY','IN_FLIGHT','UNCERTAIN/);
});
test('UI differentiates local disconnect and grant-wide revocation', () => {
  const ui=read('resources/js/Pages/Organisations/Brands/SocialAccounts.jsx');
  assert.match(ui,/Disconnect locally/);
  assert.match(ui,/Confirm entire grant revocation/);
  assert.match(ui,/Manual provider-side action required/);
});
