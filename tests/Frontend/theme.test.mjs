import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { Script } from 'node:vm';
import { fileURLToPath } from 'node:url';
import { resolve, dirname } from 'node:path';
import {
  THEME_COOKIE,
  normalizePreference,
  readThemeCookie,
  resolveTheme,
  buildThemeCookie,
  applyTheme,
} from '../../resources/js/theme/config.js';

const project = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const css = readFileSync(resolve(project, 'resources/css/theme/tokens.css'), 'utf8');
const appCss = readFileSync(resolve(project, 'resources/css/app.css'), 'utf8');

function mockRoot() {
  const classes = new Set();
  return {
    classList: {
      contains(name) { return classes.has(name); },
      toggle(name, active) { if (active) classes.add(name); else classes.delete(name); },
    },
    dataset: {},
    style: {},
  };
}

function rgb(hex) {
  return hex.slice(1).match(/.{2}/g).map((v) => parseInt(v, 16) / 255);
}
function luminance(hex) {
  const [r, g, b] = rgb(hex).map((n) => n <= 0.04045 ? n / 12.92 : ((n + 0.055) / 1.055) ** 2.4);
  return r * 0.2126 + g * 0.7152 + b * 0.0722;
}
function contrast(a, b) {
  const [l1, l2] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return (l1 + 0.05) / (l2 + 0.05);
}

test('validates all supported appearance preferences and defaults unknown values to system', () => {
  for (const value of ['light', 'dark', 'system']) assert.equal(normalizePreference(value), value);
  for (const value of ['', 'high-contrast', null, undefined, 'DARK', '__proto__']) assert.equal(normalizePreference(value), 'system');
});

test('theme cookie parsing tolerates multiple cookies, encoding and corruption', () => {
  assert.equal(readThemeCookie('session=abc; planner_theme=dark; app=test'), 'dark');
  assert.equal(readThemeCookie('planner_theme=light'), 'light');
  assert.equal(readThemeCookie('planner_theme=invalid'), 'system');
  assert.equal(readThemeCookie('planner_theme=%E0%A4%A'), 'system');
  assert.equal(readThemeCookie('unrelated=yes'), 'system');
});

test('system preference follows OS and explicit choices override OS', () => {
  assert.equal(resolveTheme('system', false), 'light');
  assert.equal(resolveTheme('system', true), 'dark');
  assert.equal(resolveTheme('light', true), 'light');
  assert.equal(resolveTheme('dark', false), 'dark');
});

test('preference cookie persists safely and uses secure flag only on HTTPS', () => {
  assert.match(buildThemeCookie('dark', true), /^planner_theme=dark; Max-Age=31536000; Path=\/; SameSite=Lax; Secure$/);
  assert.match(buildThemeCookie('system', false), /^planner_theme=system;.*SameSite=Lax$/);
  assert.ok(!buildThemeCookie('light', false).includes('Secure'));
  assert.equal(THEME_COOKIE, 'planner_theme');
});

test('theme synchronizes class, resolved theme, preference and color scheme', () => {
  const root = mockRoot();
  assert.equal(applyTheme(root, 'system', true), 'dark');
  assert.equal(root.classList.contains('dark'), true);
  assert.equal(root.dataset.theme, 'dark');
  assert.equal(root.dataset.themePreference, 'system');
  assert.equal(root.style.colorScheme, 'dark');
  assert.equal(applyTheme(root, 'light', true), 'light');
  assert.equal(root.classList.contains('dark'), false);
  assert.equal(root.dataset.theme, 'light');
  assert.equal(root.style.colorScheme, 'light');
});

test('required shadcn and semantic palette tokens are defined in light and dark', () => {
  const required = [
    'background','foreground','card','card-foreground','popover','popover-foreground',
    'primary','primary-foreground','secondary','secondary-foreground',
    'muted','muted-foreground','accent','accent-foreground','destructive',
    'border','input','ring','sidebar','sidebar-foreground','sidebar-primary',
    'chart-1','chart-5','success','warning','info','link','brand-soft',
  ];
  const light = css.split(':root.dark {')[0];
  const dark = css.split(':root.dark {')[1]?.split('@media')[0] ?? '';
  for (const name of required) {
    assert.match(light, new RegExp(`--${name}:\\s*[^;]+;`), `light theme requires ${name}`);
    assert.match(dark, new RegExp(`--${name}:\\s*[^;]+;`), `dark theme requires ${name}`);
    assert.match(appCss, new RegExp(`--color-${name}:\\s*var\\(--${name}\\)`), `Tailwind must expose ${name}`);
  }
});

test('WCAG AA normal-text contrast of explicit key palette pairs', () => {
  const pairs = [
    ['#6948d7', '#ffffff'], ['#111827', '#f7f8fc'], ['#526177', '#f7f8fc'],
    ['#b42336', '#ffffff'], ['#147d55', '#ffffff'], ['#2859a8', '#ffffff'],
    ['#a892ff', '#151026'], ['#f6f7fb', '#0b1020'], ['#b2bdd0', '#0b1020'],
  ];
  for (const [fg, bg] of pairs) assert.ok(contrast(fg, bg) >= 4.5, `${fg} on ${bg} must exceed 4.5:1`);
});

test('frontend includes an early bootstrap and prefers reduced motion', () => {
  const view = readFileSync(resolve(project, 'resources/views/app.blade.php'), 'utf8');
  const app = readFileSync(resolve(project, 'resources/js/app.jsx'), 'utf8');
  assert.match(view, /planner_theme=/);
  assert.ok(view.indexOf('planner_theme=') < view.indexOf('@vite('), 'bootstrap before CSS/JS injection');
  assert.match(app, /<ThemeProvider>/);
  assert.match(css, /prefers-reduced-motion: reduce/);
});


test('pre-paint Blade appearance script parses as valid JavaScript', () => {
  const view = readFileSync(resolve(project, 'resources/views/app.blade.php'), 'utf8');
  const script = view.match(/<script>([\s\S]*?)<\/script>/)?.[1];
  assert.ok(script, 'expected pre-paint script');
  assert.doesNotThrow(() => new Script(script));
});

test('application screens do not use old hardcoded gray/indigo utility palette', () => {
  const files = [
    'resources/js/Layouts/AuthenticatedLayout.jsx',
    'resources/js/Layouts/GuestLayout.jsx',
    'resources/js/Pages/Welcome.jsx',
    'resources/js/Pages/Dashboard.jsx',
    'resources/js/Pages/Organisations/Index.jsx',
    'resources/js/Pages/Organisations/Brands.jsx',
  ];
  for (const name of files) {
    const source = readFileSync(resolve(project, name), 'utf8');
    assert.doesNotMatch(source, /(text-gray-|bg-gray-|border-gray-|indigo-|bg-white)/, `${name} must use semantic tokens`);
  }
});
