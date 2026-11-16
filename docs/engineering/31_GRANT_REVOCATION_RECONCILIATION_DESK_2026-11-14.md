# Phase 3 / Increment 31 — Grant revocation reconciliation desk

**Baseline:** `Planner_Phase3_Durable_Grant_Revocation_2026-11-14.zip`.
**Status:** Implementation and static/frontend checks complete; Laravel/MySQL, Kinship, and live-provider tests **not executed**.

## Scope

An organisation owner can investigate revocation requests in `UNCERTAIN` or `MANUAL_REQUIRED` status without reissuing a possibly destructive remote revoke. An owner submits reference evidence; a **different** active organisation owner with Kinship `social-grant.reconcile` independently reviews the latest evidence. New routes protect the HTTP boundary and `ReconcileGrantRevocations` revalidates identity, workspace context and role for all service callers including future UTCP/MCP.

### Delivered components

- Migration: `2026_11_14_210001_create_grant_revocation_reconciliation_tables.php` (one parent composite key, two append-only child tables).
- Models/factories: `ProviderGrantReconciliationEvidence`, `ProviderGrantReconciliationDecision`, associated factories, and explicitly opt-in `GrantReconciliationDevelopmentSeeder` (local/testing only; not in default DatabaseSeeder).
- Repository: `ProviderGrantReconciliationRepositoryContract` / Eloquent implementation. Tenant-scoped queries, row locks, latest-evidence check, explicit decision history and credential cleanup.
- Business service: `ReconcileGrantRevocations` with Kinship/active-owner checks, shared validation rules, and auditable transactions.
- Web and API: resource `GrantReconciliationController@index/store` and single-action `DecideGrantReconciliationController::__invoke`; independent `social-grant.reconcile` permission, owner role required, 10/min evidence and 5/min decision rate limits.
- React/Inertia: `/organisations/{organisation:uuid}/grant-reconciliations` with evidence form, independent reviewer form and warning that manual resolution is **not** provider acknowledgement.
- CI: `npm run test:grant-reconciliation`; PHP feature/MySQL integration source.
- CI runtime hygiene: added a safe `.env.testing.example` and cache/storage directory placeholders that the baseline workflow expected but the archived project omitted.
- Baseline correction: removed a duplicated `planner:process-grant-revocations` scheduler registration and duplicate Kinship permission config entry.

### State transitions and operational semantics

`UNCERTAIN` / `MANUAL_REQUIRED` → evidence submitted (request status unchanged) → independent review:

| Reviewer outcome | Request status | Local access effect | Provider acknowledgment |
|---|---|---|---|
| `ATTESTED_REVOKED` | `MANUAL_ATTESTED_REVOKED` | Clear credentials, revoke connection, restrict accounts, detach links | **NULL** — human attestation only |
| `CONFIRMED_ACTIVE` | `MANUAL_CONFIRMED_ACTIVE` | Clear credentials, require fresh OAuth, restrict accounts | **NULL** |
| `ESCALATED` | `MANUAL_REQUIRED` | Remains restricted/unresolved; new evidence needed | **NULL** |

A `PROVIDER_SUPPORT`, `PROVIDER_CONSOLE`, or `PROVIDER_RECEIPT` reference is required for terminal decisions. An `INVESTIGATION` note permits escalation **only**. The application does not fetch external URLs, cannot verify an unsigned provider screenshot on its own and cannot claim regulatory proof of external deletion. A human must cross-check the reference within the provider's official environment. An escalation leaves the case open.

### Invariants

1. Every query is filtered by organisation and request UUID. Composite MySQL foreign keys enforce that an evidence/decision belongs to the same organisation and request.
2. Decision author cannot be evidence submitter. The review must target the *latest* evidence; superseded submissions fail with 409. The same evidence may not be reviewed twice.
3. The original grant impact digest/count must still match attached links; legacy unlinked connections and cross-organisation sharing block terminal dispositions.
4. Active refresh or inspection leases block terminal dispositions. New grant linking uses the group's lock; manual closure locks the same grant group and origin requests.
5. No provider HTTP call takes place in reconciliation. `provider_acknowledged_at` is **never written by the reconciliation service**. Retrying an uncertain remote action remains disabled.
6. Local credentials are erased only after a validated terminal decision. Existing privacy deletion obligations and unrelated brand-authored content remain separate.
7. Evidence and decision objects are append-only **through Eloquent**. Raw SQL write privileges still require database-level hardening, restricted application roles, controlled migrations and database backup/retention policy.
8. Store references and reasoned explanations only; never store API tokens, raw provider actor IDs, personal information, or screenshots with secrets. A basic token-pattern validator is a safeguard, **not** a complete secret scanner.
9. A single-owner organisation cannot self-attest terminal revocation; it can submit evidence and escalate. An independent authorised owner or a future verified provider receipt integration is required.
10. The service shares validation rules with HTTP, so future UTCP/MCP invocations cannot bypass payload validation.

### Runtime / release checklist (must execute on the user's machine)

```bash
composer install --no-interaction --prefer-dist
npm ci
php artisan migrate --env=testing --force
php artisan kinship:seed --help     # confirm installed package's exact scope/sync syntax
php artisan planner:sync-kinship-catalog --dry-run
php artisan route:list --path=grant-reconcil
php artisan test --filter=GrantReconciliationRepositoryTest
php artisan test --filter=GrantReconciliationHttpTest
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
npm run test:grant-reconciliation
npm run test:controllers
npm run build
```

Review permissions before running live catalogue sync on existing organisations. Do not run `migrate:fresh` on a database with user data. In a **throwaway** MySQL database also test `migrate:fresh`, rollback/reapply of the last migration, deadlocks, parallel different-owner decisions, cross-tenant FK violations, escalation retry with new evidence, concurrent token refresh, and crash recovery. Run provider tests only against disposable accounts. Keep `SOCIAL_REMOTE_REVOCATION_ENABLED=false` until all previous revocation gates have passed.

### Known limitations and next increment

- No live provider reconciliation endpoint exists; terminal outcomes are **manual human attestations**, not independently cryptographic provider proof.
- Final MySQL behaviour, Kinship permission syncing, runtime role grant behavior, and transaction deadlock resilience require user testing.
- Review reference URLs are displayed only; they are not fetched. Security review must address sensitive strings in submitted `summary`, privacy/legal retention windows, and database permissions for append-only evidence.
- Current directory lists the 100 most recent unresolved cases. Add cursor pagination, SLA/assignment alerts and search once volume requires it.
- Resume provider-specific capability checks with verified grant/app user identity; next major product area is Tetranyble Storage-backed content drafts followed by approval-gated durable publishing.
- **Pensuh.com**: privacy policy, terms, cookie consent, user data deletion instructions and relevant legal pages remain a separate, NOT-DEPLOYED workstream. See `docs/compliance/PENSUH_COM_PRIVACY_AND_LEGAL_PAGES_WORKSTREAM.md`.

## Source-based validation evidence

- `php -l`: 832 `app`, `tests`, `database`, and `routes` PHP files passed (re-run on final archive).
- `python scripts/check_migration_structure.py`: 111 application-owned table definitions passed static dependency checks.
- `node --test tests/Frontend/*.test.mjs`: **65 passed** after making the cached `@babel/parser` available in the temporary workspace; that dependency is already declared in package.json. No node_modules shipped.
- Repository and Kinship static guards passed. **No real MySQL / PHPUnit / HTTP live-provider test has run in this environment.**

### Environment-specific blocker observed

The upload excluded `vendor/`; a cached vendor directory from an older workspace shared the same Composer lockfile but contained an incompatible generated autoloader. The initial Laravel test command failed due to missing `vendor/autoload.php`; a probe against that old vendor directory could not be used to validate the current source. This container also lacks `pdo_mysql`, `mbstring`, `intl`, and normal Composer install. **Do not infer a Laravel regression from that probe.** Install dependencies from this source directory on the user's PHP/MySQL environment, regenerate Composer autoload files, and execute all listed runtime tests.
