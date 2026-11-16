import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { parse } from '@babel/parser';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const source = (path) => readFileSync(resolve(root, path), 'utf8');

test('brand membership screen parses and uses semantic theme tokens', () => {
    const text = source('resources/js/Pages/Organisations/Brands/Members.jsx');
    assert.doesNotThrow(() => parse(text, { sourceType: 'module', plugins: ['jsx'] }));
    assert.match(text, /AuthenticatedLayout/);
    for (const token of ['bg-card', 'text-foreground', 'border-border', 'bg-background']) {
        assert.ok(text.includes(token), token);
    }
});

test('member list uses checked routes and preserves request progress controls', () => {
    const text = source('resources/js/Pages/Organisations/Brands/Members.jsx');
    for (const name of ['organisations.brands.members.index', 'organisations.brands.members.add',
        'organisations.brands.members.change-role', 'organisations.brands.members.revoke']) {
        assert.ok(text.includes(name), name);
    }
    assert.match(text, /form\.processing/);
    assert.match(text, /role="alert"/);
    assert.match(text, /window\.confirm/);
});

test('brand membership UI does not expose storage or OAuth credentials', () => {
    const text = source('resources/js/Pages/Organisations/Brands/Members.jsx');
    assert.doesNotMatch(text, /token_hash|encrypted_token|access_token|client_secret|refresh_token/);
    assert.match(text, /eligibleRoles\(directory\.roles, candidate\?\.organisation_role\)/);
});
