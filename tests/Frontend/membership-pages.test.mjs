import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { parse } from '@babel/parser';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const source = (path) => readFileSync(resolve(root, path), 'utf8');

test('membership and invitation JSX parse and keep the existing theme primitives', () => {
    for (const path of ['resources/js/Pages/Organisations/Members.jsx', 'resources/js/Pages/Organisations/AcceptInvitation.jsx']) {
        const text = source(path);
        assert.doesNotThrow(() => parse(text, { sourceType: 'module', plugins: ['jsx'] }));
        assert.match(text, /AuthenticatedLayout/);
        assert.match(text, /className="[^"]*(?:text-foreground|text-card-foreground)/);
    }
});

test('invitations and membership changes use named routes and disabled pending states', () => {
    const text = source('resources/js/Pages/Organisations/Members.jsx');
    for (const name of ['organisations.members.invite', 'organisations.members.change-role', 'organisations.members.revoke', 'organisations.members.cancel-invitation']) {
        assert.ok(text.includes(name), name);
    }
    assert.match(text, /aria-invalid=/);
    assert.match(text, /disabled=\{inviteForm.processing\}/);
    assert.match(text, /role="alert"/);
    assert.match(source('resources/js/Pages/Organisations/AcceptInvitation.jsx'), /organisation.invitations.accept/);
});

test('member screens do not expose plaintext invitation tokens on listing pages', () => {
    const text = source('resources/js/Pages/Organisations/Members.jsx');
    assert.doesNotMatch(text, /token_hash|encrypted_token|access_token/);
});
