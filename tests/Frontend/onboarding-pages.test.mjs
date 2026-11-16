import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parse } from '@babel/parser';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
function source(path) { return readFileSync(resolve(root, path), 'utf8'); }

const pages = [
    'resources/js/Pages/Organisations/Create.jsx',
    'resources/js/Pages/Organisations/Brands/Create.jsx',
];

test('onboarding JSX can be parsed without modifying the shadcn setup', () => {
    for (const file of pages) {
        assert.doesNotThrow(() => parse(source(file), { sourceType: 'module', plugins: ['jsx'] }), file);
    }
});

test('onboarding forms expose accessible labels, errors and disabled submit state', () => {
    for (const file of pages) {
        const content = source(file);
        assert.match(content, /<label htmlFor=/, file);
        assert.match(content, /aria-invalid=/, file);
        assert.match(content, /role="alert"/, file);
        assert.match(content, /disabled=\{processing\}/, file);
        assert.match(content, /event\.preventDefault\(\)/, file);
        assert.doesNotMatch(content, /\b(?:text-gray-|bg-white|indigo-)\b/, file);
    }
});

test('organisation and brand creation target the server-authorized named routes', () => {
    assert.match(source(pages[0]), /route\('organisations\.store'\)/);
    assert.match(source(pages[1]), /route\('organisations\.brands\.store'/);
    assert.match(source('resources/js/Pages/Organisations/Index.jsx'), /route\('organisations\.create'\)/);
    assert.match(source('resources/js/Pages/Organisations/Brands.jsx'), /canCreateBrand/);
});
