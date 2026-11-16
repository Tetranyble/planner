# Increment 61 — Official Laravel MCP, provider token evidence, and durable operational alerts

**Date:** 2026-11-15. **Source implementation milestone:** estimated ~90% across the five planned batches. **NOT production-ready; 0/5 accepted.** This work derives from Increment 60 and preserves its complete source, all existing migrations and both dependency lockfiles. Final Laravel/MySQL/third-party/browser tests are deferred to the user's system.

## Deliverables

### 1. Official read-only Laravel MCP server (Batch 5)

- Added `laravel/mcp: ^1.2` to **composer.json**, but **did not install vendor code** because Composer and required extensions are unavailable in this environment. `composer.lock` was **not deleted, edited, or regenerated**. It is intentionally not yet synchronized with the changed manifest. Local `composer install` will therefore need reconciliation (below).
- Registered `Mcp::web('/mcp/planner', PlannerReadOnlyServer::class)` in `routes/ai.php` with Sanctum authentication, dedicated tenant-grant middleware, rate limiting, and explicit kill switch `PLANNER_MCP_READ_ENABLED=false`.
- Five official MCP tools: brand operations, publication calendar, operational safety, provider-specific performance, and publication history. All are explicitly `IsReadOnly` and `IsIdempotent`. No media uploading, posting, retries, approvals, token creation or scheduling tools are exported.
- Middleware requires **a real Sanctum personal access bearer token**, not session cookies or transient SPA tokens. Abilities must be exactly `['planner:read']`, token owner must match, grant must be unexpired and unrevoked; its one organisation/brand is derived **server-side from the grant record** rather than tool-supplied IDs. Current active org membership, brand membership, and Kinship permission are rechecked. Browser Origin is checked against a literal exact-origin allowlist; HTTPS is required outside local/test. The original REST/UTCP read-only routes remain unchanged.
- Shared `PlannerMcpReader` invokes the identical service layer as REST/UTCP with independent service authorization, bounded date ranges and a 64 KB tool response cap.
- Added `planner:audit-mcp-release --json [--strict]` with sanitized source/platform/DB gates. No OAuth dynamic client registration or Passport was added; delegated ChatGPT OAuth is **not** implemented. The MCP server is source-authored, not runtime-installed or externally verified.

### 2. AI provider token evidence (Batch 3)

- Additive columns `brand_ai_usage_reservations.provider_input_tokens`, `.provider_output_tokens` (nullable). Uses typed `ProviderTokenEvidence`, recording only exact non-negative provider integer counters. Absence/invalid evidence remains `null`, never fabricated zero. No price inference or USD cost claim.
- Both saved-copy and campaign-proposal generation pass the provider's usage counters to the existing settled AI reservation. Stored usage remains brand-scoped and idempotent; the usage read API adds observed token totals and a count of successes with missing evidence. Cost availability remains false. Generation and human-review permissions unchanged.

### 3. Durable operational alert history (Batch 4)

- Two additive MySQL tables for brand-scoped alert signals and append-only transition evidence. Composite organisation+brand foreign keys and a unique signal key protect tenant isolation and idempotency.
- `OperationalAlertMonitor` captures the existing production safety counts. Brand-row locking serializes detection. Each signal opens, changes, resolves (only when measured count returns to 0), or reopens with a corresponding evidence event. No provider request, retry, automatic publication repair, external alert destination, or secret storage.
- Opt-in `planner:scan-operational-alerts --limit=10000 --after-id=0` scheduled daily, default `PLANNER_OPERATIONAL_ALERT_SCAN_ENABLED=false`. Supports keyset resume for large deployments. Brand-facing React safety panel distinguishes historical alerts from current observed counts.

## Dependency constraints and local instructions

**Do not delete either lockfile automatically.** There is no Composer binary in the construction environment, and PHP extensions `mbstring`, `intl`, `dom`, `xmlwriter`, `pdo_mysql` are absent. A locked vendor installation, Laravel route boot, PHPUnit and real MySQL tests could not execute here.

On a **disposable local/test environment** with PHP >= 8.3, MySQL 8, Composer, `intl`, `mbstring`, `pdo_mysql`, `dom`, `xmlwriter` and working existing Kinship/Storage package credentials:

```bash
# Inspect package constraint differences first; old composer.lock intentionally preserved.
composer validate --no-check-publish
composer update laravel/mcp --with-all-dependencies
composer install
npm ci
# Existing, populated production DB: do NOT migrate:fresh.
php artisan migrate --force
php artisan route:list --path=mcp
php artisan planner:audit-mcp-release --json
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
node --test tests/Frontend/*.test.mjs
npm run build
```

`composer update laravel/mcp -W` is a **suggested lock-reconciliation step**, not something performed in this ZIP. Inspect the dependency diff; if Composer finds a constraint conflict, resolve it locally as agreed. Do not turn on MCP or publishing flags until route middleware, Kinship, version pinning and cross-tenant tests are proven. Feature tests must cover revoked token, expired token, cookie-only request, wrong Origin, cross-brand grant, suspended membership, denied Kinship permission, 429, cache headers, and MCP `tools/list` + `tools/call` transport.

Before enabling controlled external reads, set HTTPS `APP_URL`, `APP_DEBUG=false`, `PLANNER_INTEGRATION_READ_ENABLED=true`, `PLANNER_MCP_READ_ENABLED=true`, and set `PLANNER_MCP_ALLOWED_ORIGINS` to explicit browser origins if needed. A missing Origin is allowed only for non-browser token clients. Restart config cache and run `planner:audit-mcp-release --strict`. Do not expose raw bearer tokens in build logs, issue tokens via MCP, or enable remote posting. External delegated OAuth 2.1 / Passport and ChatGPT host compatibility remain separately unverified.

For the read-only operational alert scan, use `PLANNER_OPERATIONAL_ALERT_SCAN_ENABLED=true` only after validating MySQL migrations, queue/scheduler configuration, isolation and alert history. Alerts are only observed DB state; a resolved signal does not imply a provider confirmed delivery or human dispute resolution.

## Actual validation executed here

- Portable provider-usage normalization assertions: passed.
- Focused new MCP/monitoring frontend safety suite: 10/10 passed.
- Complete frontend suite: **187/190 pass**. Three existing suites cannot import `@babel/parser` because dependencies are not installed here.
- PHP syntax: **1291 PHP source files pass** (including new migrations, models, services, middleware and tests).
- Repository boundary guard: 51 repositories passed.
- Kinship catalog: 128 model files, 928 discovered/special permissions, 4 roles; guard passed.
- Controller convention suite: 4/4 passed.
- MySQL migrations, PHPUnit, MCP HTTP/JSON-RPC, live provider, browser E2E, Composer installation and CI: **NOT EXECUTED**.
- `composer.lock` and `package-lock.json`: source hashes exactly preserved from the Increment 60 input.

## Updated source-coverage estimates (engineering judgment; not verified readiness)

| Batch | Previous | Increment 61 | Accepted? |
|---|---:|---:|---|
| 1. Publishing Engine | 95% | **95%** | No — real provider/Storage validation |
| 2. Content Operations | 95% | **95%** | No — full browser, media/runtime verification |
| 3. AI Marketing Intelligence | 76% | **82%** | No — model cost, policy and tests |
| 4. Analytics & Automation | 80% | **88%** | No — other provider ingestion, alert delivery and verification |
| 5. Integrations & Production | 78% | **90%** | No — installed MCP, delegated OAuth, security/backup/deployment validation |
| **Unweighted source estimate** | **84.8%** | **90.0%** | **0/5 accepted** |

## Outstanding high-priority work

1. Resolve Composer lock against `laravel/mcp`; run actual MCP server with token revocation and tenant-scoped tools/list/call against test MySQL and an MCP client; consider OAuth 2.1 only if the chosen host needs it.
2. Verify and implement the real Tetranyble Storage byte-reader/media distribution contract. Keep Instagram/TikTok/YouTube provider publishing OFF pending capability approvals, media QA, and provider receipts.
3. Complete provider metrics expansion, alert delivery escalation, AI usage cost reconciliation, operational lifecycle QA, disaster recovery, automated backups, secrets/key rotation and production deployment.
4. Run MySQL migration/rollback, all PHPUnit/CI/browser tests, cross-org authorization, Kinship sync, OAuth provider and retention/Meta deletion flow E2E.
5. Pensuh.com public legal/privacy/cookie/data-deletion pages are still tracked, **not deployed**.

Public package references (reviewed 2026-11-15):
- https://packagist.org/packages/laravel/mcp
- https://laravel.com/framework/docs/13.x/mcp
- https://packagist.org/packages/spatie/laravel-health (optional, **not added** because existing `/up` and custom health auditor cover the present needs)
