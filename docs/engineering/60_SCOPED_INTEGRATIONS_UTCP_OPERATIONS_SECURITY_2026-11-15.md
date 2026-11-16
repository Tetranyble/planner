# Increment 60 — Scoped external integrations, UTCP, and operational safety

**Status:** Source implemented. No Laravel/MySQL runtime, HTTP browser, MCP transport, or third-party client E2E validation has been executed in this environment. Publishing remains disabled by default. **Five-batch engineering estimate: ~85%** (an unweighted rounded average of B1 95%, B2 95%, B3 76%, B4 80%, B5 78%). **0/5 batches accepted.** These are subjective code-scope estimates, not test coverage or production-readiness scores.

## Implemented source

1. **Scoped Sanctum read-only tokens:** `User` now uses `HasApiTokens`; additive migrations create Sanctum's compatible table only if absent, and add `planner_integration_grants` with a composite organisation/brand foreign key, token owner, expiry and revoked_at. Integration credentials are issued by a first-party, CSRF-protected session only, with owner/manager Kinship, active organisation+brand membership, and second service check. External `planner:read` tokens are scoped to exactly one organisation, brand, and user, last at most 30 days, are shown once, and can be revoked. A bounded hourly pruning command removes expired bearer credentials without deleting grant evidence. Revocation removes the underlying Sanctum token but preserves the grant audit record. An hourly bounded pruning command revokes expired credentials.
2. **Read-only external API:** `/api/v1/integrations/organisations/{orgUuid}/brands/{brandUuid}` offers `operations?from&to`, `calendar?from&to`, `provider-performance`, `publication-operations`, `safety` and `utcp`. An API-wide isolation middleware first prohibits these read-only bearer tokens from **all normal API routes**, including legacy POST/PUT/DELETE endpoints. Every permitted read requires `auth:sanctum`, `verified`, selected organisation context, genuine `PersonalAccessToken` (not a first-party transient token), `planner:read`, matching persisted scope, current Kinship permission and service-level authorisation. Read routes have no mutations, per-minute throttling, no-store responses, and the global default-off switch `PLANNER_INTEGRATION_READ_ENABLED=false`.
3. **UTCP v1.1 manual:** Authenticated tenant-scoped discovery exposes precisely five GET tools with JSON input schemas, fixed tenant route URLs and bearer API-key templating. This is a UTCP HTTP provider manual; **it is not an MCP server**, OAuth delegation, or a ChatGPT plugin installation. No posting, approval, scheduling, or content mutations are exposed.
4. **React management and operator safety:** The content studio supports first-party token issuance, one-time copying, revocation and status. Its independent operational view shows only brand-scoped aggregates: publication uncertainties/failures/stale leases/overdue queue, media uncertainty/stale leases, and provider-metrics failures/stale leases. It does not claim provider success or perform retries.
5. **Deployment tooling:** `planner:audit-integration-release --json --strict` checks HTTPS, debug mode, DB reachability, token/grant schemas, extension readiness and the read-only release switch without exposing secrets. `.github/workflows/dependency-security.yml` adds locked Composer dependency audits, production npm audits, and integration static safety tests. CI was not run here.

## Packages considered (public documentation reviewed 2026-11-15)

| Package | Evidence | Decision |
|---|---|---|
| `laravel/sanctum ^4.0` | Already locked in Planner's composer.json | **Reused** for expiring, revocable brand-scoped personal access credentials. No duplicate auth/token hashing implementation. |
| `laravel/mcp ^1.2` | https://laravel.com/framework/docs/13.x/mcp ; Packagist `laravel/mcp` v1.2.0 supports Laravel 13 / PHP 8.2+ | **Recommended for a subsequent dependency-approved integration. Not installed**: Composer cannot run here and `composer.lock` must be kept in sync. A real MCP server, OAuth/consent, and ChatGPT environment tests remain outstanding. |
| `spatie/laravel-health ^1.41` | https://packagist.org/packages/spatie/laravel-health | Optional future add-on for operational health. Planner already has `/up`, Sentry, and Telescope, so a second health subsystem is not added without runtime verification. |
| UTCP v1.1 HTTP plugin (Python ecosystem) | https://www.utcp.io/protocols/http | **Not a required PHP dependency**: Planner returns a UTCP-compliant HTTP manual over existing REST routes. Client validation remains pending. |

No new Composer dependencies or lockfile changes were made.

## Source tests authored and currently runnable checks

- `tests/Frontend/planner-integrations-architecture.test.mjs` (6 test cases)
- `tests/Frontend/planner-operational-safety.test.mjs` (2 test cases)
- `tests/Unit/Integrations/PlannerIntegrationTokenMiddlewareTest.php` (2 cases; Laravel runtime pending)
- `tests/Unit/Integrations/PlannerIntegrationBearerIsolationTest.php` (4 cases: legacy GET/POST denial and dedicated GET allowance; Laravel runtime pending)
- `tests/Integration/Integrations/PlannerIntegrationGrantPersistenceTest.php` (2 MySQL cases; runtime pending)
- `tests/Integration/Integrations/PlannerOperationalSafetyPersistenceTest.php` (1 MySQL case; runtime pending)
- Existing `tests/Frontend/controller-conventions.test.mjs`, Kinship/repository guards, PHP syntax and full frontend regression.

## Mandatory local release gate

1. Install locked Composer dependencies and JS packages (`composer install`, `npm ci`) on a disposable MySQL 8/PHP 8.4 environment. Make sure `ext-intl`, `pdo_mysql`, `mbstring`, `dom`, `xmlwriter`, `fileinfo` and package services are present. Do not use `migrate:fresh` on an existing user database.
2. Run normal additive migrations and test up/down on the disposable database. If `personal_access_tokens` already exists, the compatibility migration leaves it untouched; its down() is deliberately non-destructive to avoid erasing credentials from other Sanctum consumers.
3. Run `php artisan test --testsuite=Unit`, `Feature`, `Integration`; `node --test tests/Frontend/*.test.mjs`; `npm run build`; `php artisan route:list --path=integrations`; and `php artisan planner:audit-integration-release --json`. Test migrations' composite scope FK, concurrency, token issue+revoke, expired token cleanup, middleware and current permissions.
4. Verify a first-party manager can create a 1–30-day token, that raw key appears once, and that a bearer token cannot mint a further token; no token/session may cross brands, organisations or users. Revoke a role or brand membership and verify access immediately stops. Test 401, 403, 404, 422 and 429 responses. Specifically verify `planner:read` bearer tokens cannot call existing `/api/v1/organisations/...` mutation or GET APIs under any circumstances, even if the token owner normally has manager permissions.
5. With integration flag OFF, external reads must be disabled. Only in a controlled HTTPS test environment with audited permissions set `PLANNER_INTEGRATION_READ_ENABLED=true` and use `Authorization: Bearer <one-time-token>` to call the brand's `/utcp` and read endpoints. Check response cache headers, no secrets/PII, correct UTC ranges, no cross-platform engagement totals, no publication side effects.
6. Run the new GitHub Actions security workflow. Confirm audit output and handle any vulnerabilities; do not presume green CI from authored YAML.
7. The official MCP package is not installed, there is no remote OAuth 2.1 delegation, no token exchange for ChatGPT, and no live provider test. Continue to keep every publishing gate disabled until independently verified. Pensuh.com legal/privacy/cookie/data-deletion public pages remain **not deployed**.

## Acceptance boundary

B1 95%: Live provider media delivery and Storage API integration / MySQL concurrency unresolved. B2 95%: Full media and browser E2E unresolved. B3 76%: Cost accounting, model reconciliation, human-review E2E remain. B4 80%: More authorized provider metrics and alerts/delivery automation remain. B5 78%: Read-only UTCP REST and integration credentials implemented, but official MCP/OAuth, production security review, backup/restore, provider approvals, privacy rollout and deployment verification remain. **0/5 accepted.**
