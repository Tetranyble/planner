# Phase UI-01 — Theme foundation and design tokens

**Date:** 2026-11-14  
**Baseline:** `Planner_Phase1_Organisation_Brand_Directory_2026-11-14.zip`.  
**Implementation state:** SOURCE IMPLEMENTED, FRONTEND UNIT/STATIC TESTS PASS; PRODUCTION BUILD AND BROWSER TESTS PENDING.

## Source provenance and limitation

The user requested reuse of the **current Pensuh Next.js marketing app tokens**. That actual source was **not available** in the currently supplied Planner archive or the accessible File Library. The accessible `PENSUH_B2B_WEBSITE_BLUEPRINT.md` (November 6, 2026), Section 6, suggests these colors *subject to brand approval*: primary `#6948D7`, navy `#111827`, white `#FFFFFF`, mist `#F7F8FC`, border `#E6E9F1`, supporting gray `#64748B`, violet wash `#F2EEFF`, optional blue `#3B82F6`. It suggests Geist or Inter typography. **Do not represent these as exact tokens from the deployed Pensuh site.**

Until the Pensuh `globals.css` and theme definitions are supplied, this **provisional preset** is used to exercise the architecture. The token mapping is isolated so exact values can be updated with minimal code changes.

## Design decisions

1. **Primitive → semantic → shadcn/Tailwind layering.** Raw brand values live only in `resources/css/theme/tokens.css`. Feature components use semantic utilities such as `bg-card`, `text-muted-foreground`, `border-border`, `bg-primary`, `text-destructive` rather than numeric colors.
2. **Dark/light/system** theme is a global *user appearance preference*, not a brand permission or an organisation setting. A normal non-sensitive `planner_theme` cookie persists the choice for one year (SameSite=Lax, Secure on HTTPS). The system option follows `prefers-color-scheme` and updates dynamically.
3. **Prevent flash of incorrect theme.** The HTML layout runs a tiny theme bootstrap in `<head>` before the Vite-injected assets. It intentionally mirrors `resources/js/theme/config.js`; keep both in sync. With a strict CSP, supply a nonce or script hash instead of allowing arbitrary inline scripts.
4. **Geist bundled locally** via the existing `@fontsource-variable/geist` dependency. Removed the unused remote Figtree request and Tailwind's Figtree override. No font files were added to the archive.
5. **Accessible controls.** The appearance selector uses buttons with `aria-label`, `aria-pressed` and focus outlines; roles and states have semantic colors, adequate contrast, and a reduced-motion path.
6. **Multi-brand future compatibility.** The shell theme is distinct from individual brand identities. Do not automatically switch the entire Planner interface when an organisation's marketing brand changes. Marketing previews may later use explicit sandboxed brand styling.
7. **No database migration.** No tenant-level theme configuration was added; the global preference stays client-side until a real cross-device requirement is defined.

## Files and responsibilities

| Path | Responsibility |
|---|---|
| `resources/css/theme/tokens.css` | Provisional Pensuh primitives, shadcn semantic roles for light/dark, chart/status/sidebar colors, typography, shape, elevation and motion |
| `resources/css/app.css` | Tailwind v4 `@theme inline` role exports, base typography, focus and selection |
| `resources/js/theme/config.js` | Pure preference normalisation, cookie parsing/serialization, resolved mode, DOM synchronisation |
| `resources/js/theme/ThemeProvider.jsx` | React context, change listeners, persistence, immediate application |
| `resources/js/theme/ThemeSwitcher.jsx` | Accessible Light/Dark/System controls |
| `resources/views/app.blade.php` | Pre-paint theme bootstrap |
| `resources/js/app.jsx` | Global provider and Inertia progress color token |
| `resources/js/Layouts/*`, `resources/js/Pages/*`, `resources/js/Components/*` | Semantic color utilities on the shell, authentication, welcome, organisation directory and profile views |
| `tests/Frontend/theme.test.mjs` | Token coverage, preference unit tests, cookie behavior, contrast and bootstrap assertions |

## Token families

- **Color roles:** background, foreground, card, popover, primary, secondary, muted, accent, destructive, border, input, ring, link, brand-soft, success, warning, info, sidebar, charts 1–5.
- **Typography:** Geist UI stack, monospace code, named body and heading scales, line heights.
- **Shape:** base/control/card radii.
- **Layout:** max content width, responsive gutter, control heights, Tailwind spacing defaults.
- **Effects:** card/popover elevation and normal/fast motion durations.

Light mode uses `#6948D7` for primary action and white text; dark mode uses a lighter violet primary with near-black text. The original Pensuh blueprint did not define a dark palette. The dark-mode values are an **implementation proposal**, not source-derived tokens.

## Verification performed in this environment

- `npm run test:theme`: 10/10 tests passed (Node 22.16.0).
- Babel parser validation: 34 JS/JSX files passed syntax parse.
- Full `vite build`: **NOT PASSED**; installed node_modules were copied from a macOS environment and lack Linux `@rolldown/binding-linux-x64-gnu`. Run `npm ci && npm run build` on a supported Linux or macOS development environment with working package installation.
- PHPUnit/MySQL tests: **NOT RUN**; no schema or backend code was modified by this theme slice.
- Browser/mobile/a11y screenshots: **NOT RUN**. Must verify with real browser and responsive devices.

## Remaining verification and product decisions

1. Obtain the exact current Pensuh Next.js `src/app/globals.css`, any `styles/tokens.css`, Tailwind config, `next-themes`/theme provider logic, and shadcn `components.json`. Diff semantic token values and copy exact *approved* colors, radii, fonts, inputs and focus styles into this isolated token file.
2. Run `npm ci`, `npm run test:theme`, `npm run build`, PHP tests and CI in the user's validated environment.
3. Verify no flash on refresh in Light, Dark and System; OS theme change while System is active; persistence after navigating and refreshing; keyboard/focus visibility; contrast of disabled, muted and semantic alert states.
4. Audit chart/graph color contrast, browser form controls, profile delete-dialog, overflow and mobile-menu layout.
5. Capture visual baseline screenshots for the public, login, dashboard and organisation/brand pages. Add Playwright tests when browser test infrastructure is provisioned.
6. Decide whether a separate Planner corporate brand palette is desired after Pensuh colors are verified.

## Next feature task

This UI infrastructure is a cross-cutting prerequisite, **not a closure of Phase 0 runtime gates**. Continue with existing `CONTINUE_HERE.md` roadmap: run real MySQL/Kinship/Storage CI verification, then organisation onboarding + brand write lifecycle. Keep the repository pattern unchanged.
