import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const file = (name) => readFileSync(new URL('../../' + name, import.meta.url), 'utf8');

test('OAuth callback is invokable and uses a fixed provider callback route', () => {
    const route = file('routes/web.php');
    const ctl = file('app/Http/Controllers/Workspace/CompleteSocialOAuthController.php');
    assert.match(route, /integrations\/social\/\{provider\}\/callback/);
    assert.match(ctl, /function __invoke\(/);
    assert.doesNotMatch(ctl, /function (?:add|approve|connect|revoke)\(/);
});

test('OAuth initiation and destruction enforce Kinship permissions at the route boundary', () => {
    const route = file('routes/web.php');
    assert.match(route, /kinship\.permission:social-account\.connect/);
    assert.match(route, /kinship\.permission:social-account\.disconnect/);
    assert.match(route, /kinship\.permission:social_account\.index/);
    assert.match(route, /organisation\.context/);
});

test('social UI uses semantic theme classes and does not show provider secrets', () => {
    const source = file('resources/js/Pages/Organisations/Brands/SocialAccounts.jsx');
    assert.match(source, /bg-card/);
    assert.match(source, /text-muted-foreground/);
    assert.match(source, /Connect/);
    assert.doesNotMatch(source, /access_token|refresh_token|client_secret/);
});

test('storage ciphertext is hidden from serialization and hashed state is one-use', () => {
    assert.match(file('app/Models/ProviderConnectionCredential.php'), /protected \$hidden/);
    assert.match(file('app/Models/SocialOauthAttempt.php'), /state_digest/);
    const repo = file('app/Repository/Eloquent/SocialConnectionRepository.php');
    assert.match(repo, /lockForUpdate/);
    assert.match(repo, /consumed_at/);
});

test('no automatic publish capability is claimed before platform approval', () => {
    assert.match(file('app/Social/Providers/TikTokOAuthProvider.php'), /'ASSISTED'/);
    assert.match(file('app/Social/Providers/YouTubeOAuthProvider.php'), /'UNAVAILABLE'/);
});
