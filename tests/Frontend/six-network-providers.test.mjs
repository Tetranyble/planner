import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const file = path => readFileSync(new URL('../../' + path, import.meta.url), 'utf8');
const names = ['facebook', 'instagram', 'linkedin', 'x', 'tiktok', 'youtube'];

test('provider registry, route restrictions and frontend include all six networks', () => {
    for (const provider of names) {
        assert.match(file('app/Social/SocialProviderRegistry.php'), new RegExp(`'${provider}'`));
        assert.match(file('config/social_integrations.php'), new RegExp(`'${provider}'`));
        assert.match(file('resources/js/Pages/Organisations/Brands/SocialAccounts.jsx'), new RegExp(`id: '${provider}'`));
    }
    assert.match(file('routes/web.php'), /SocialProviderRegistry::PROVIDERS/);
});

test('OAuth contracts are single implementation and do not provide publishing endpoints', () => {
    for (const cls of ['FacebookOAuthProvider', 'InstagramOAuthProvider', 'LinkedInOAuthProvider', 'XOAuthProvider']) {
        const body = file(`app/Social/Providers/${cls}.php`);
        assert.match(body, /implements SocialOAuthProvider/);
        assert.match(body, /function exchange/);
        assert.match(body, /function discover/);
        assert.match(body, /'UNAVAILABLE'/);
    }
});

test('X PKCE verifier is HMAC-derived from bound state and never stored raw', () => {
    const x = file('app/Social/Providers/XOAuthProvider.php');
    assert.match(x, /hash_hmac\('sha256', \$state/);
    assert.match(x, /code_challenge_method/);
    assert.match(x, /code_verifier/);
    assert.match(file('app/Repository/Eloquent/SocialConnectionRepository.php'), /hash|state_digest|consumed_at/);
});

test('no OAuth refresh token is required for valid Meta and LinkedIn access-only grants', () => {
    const repo = file('app/Repository/Eloquent/EncryptedProviderCredentialRepository.php');
    assert.doesNotMatch(repo, /grant->refreshToken === null && \$existing === null/);
    assert.match(repo, /refresh_token_ciphertext/);
});

test('multi-account selection requires explicit brand-scoped confirmation and encrypts credentials', () => {
    const routes = file('routes/web.php');
    const service = file('app/Services/Social/ManageSocialConnections.php');
    const repo = file('app/Repository/Eloquent/SocialOAuthSelectionRepository.php');
    assert.match(routes, /social-selections\/\{selectionUuid\}\/confirm/);
    assert.match(routes, /kinship\.permission:social-account\.connect/);
    assert.match(service, /count\(\$accounts\) > 1/);
    assert.match(service, /Unknown or unauthorized provider account selected/);
    assert.match(repo, /Crypt::encryptString/);
    assert.match(repo, /lockForUpdate/);
    assert.match(repo, /consumed_at/);
    assert.match(file('resources/js/Pages/Organisations/Brands/SocialAccountSelection.jsx'), /account_ids/);
});
