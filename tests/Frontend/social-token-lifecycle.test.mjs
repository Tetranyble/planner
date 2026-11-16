import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
const source = path => readFileSync(new URL('../../' + path, import.meta.url), 'utf8');

test('provider refresh interfaces implemented only where supported', () => {
  for (const name of ['TikTokOAuthProvider','YouTubeOAuthProvider','LinkedInOAuthProvider','XOAuthProvider']) {
    const code = source(`app/Social/Providers/${name}.php`);
    assert.match(code, /implements SocialOAuthProvider, \\App\\Social\\Contracts\\RefreshableSocialProvider/);
    assert.match(code, /public function refresh\(string \$refreshToken\)/);
  }
  for (const name of ['FacebookOAuthProvider','InstagramOAuthProvider']) {
    assert.doesNotMatch(source(`app/Social/Providers/${name}.php`), /RefreshableSocialProvider/);
  }
});

test('refresh lease uses database locks and fails closed on stale leases', () => {
  const code = source('app/Repository/Eloquent/ProviderTokenLifecycleRepository.php');
  assert.match(code, /lockForUpdate\(/);
  assert.match(code, /REFRESH_OUTCOME_UNKNOWN/);
  assert.match(code, /SocialAccountCapability::query\(/);
  assert.match(code, /Crypt::decryptString/);
  assert.doesNotMatch(code, /Log::|dd\(|var_dump\(/);
});

test('provider I/O runs outside DB transaction and scheduler is registered', () => {
  const code = source('app/Services/Social/RenewSocialCredentials.php');
  assert.match(code, /\$adapter->refresh\(\$lease->refreshToken\)/);
  assert.doesNotMatch(code, /DB::transaction/);
  assert.match(source('routes/console.php'), /planner:renew-social-credentials/);
  assert.match(source('app/Console/Commands/RenewSocialCredentials.php'), /--limit/);
});

test('connection health is visible without exposing secrets or automatic posting', () => {
  const repo = source('app/Repository/Eloquent/SocialConnectionRepository.php');
  const screen = source('resources/js/Pages/Organisations/Brands/SocialAccounts.jsx');
  assert.match(repo, /reconnect_reason/);
  assert.match(repo, /token_expires_at/);
  assert.match(screen, /needsReconnect/);
  assert.match(screen, /Reconnect/);
  assert.doesNotMatch(screen, /access_token_ciphertext|refresh_token_ciphertext/);
});
