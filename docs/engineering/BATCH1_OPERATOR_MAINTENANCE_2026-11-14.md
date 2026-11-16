# Batch 1 / Batch 2 shared operator workflow — held publication maintenance

## Implemented in this source checkpoint

- Tenant-scoped, Kinship-gated `POST .../publication-intents/{jobId}/reschedule` (owner/manager) takes ISO-8601 offset-aware `run_at` plus `expected_version` optimistic lock. Locks organisation, brand, then target job; refuses non-held or leased work; rechecks approval/media snapshot before changing time. Original occurrence key and approval snapshot remain unchanged. Writes audit in transaction.
- Tenant-scoped `POST .../publication-intents/bulk-cancel` (owner/manager) takes 1–50 IDs. Locks all selected jobs in sorted order and validates every job before making any mutation, so mixed active and held records abort the entire operation. Repeated cancelled IDs are idempotent. Audits changed rows.
- Reuses existing repository contracts, Eloquent models, stored `publication_jobs.version`, Kinship, and audit service. No migration required.
- React desk exposes optimistic version checks, rescheduling, multi-selection and bulk cancellation. Calendar adds state filtering and client-side CSV export of tenant-authorized returned rows; CSV is **not** a published-post report.
- Added source integration test for Eloquent repository context/reschedule and a frontend architecture regression suite. Laravel/MySQL/Playwright testing is deferred to the user's environment.

## Threat model and invariants

- Every service entry checks organisation + brand scope and permission; route middleware additionally verifies role and Kinship action permission.
- `AWAITING_USER` must never be interpreted as `SCHEDULED`. Rescheduling does not change `state`, `max_attempts`, credentials or worker leases.
- A changed/expired approval, updated content hash, or changed active policy prevents rescheduling; provider-readiness-only blockers can still be held.
- Concurrent edits are rejected at the database row lock when `expected_version` is stale.
- Bulk operation is atomic including audit writes; nonexistent or cross-brand plan IDs abort all cancellations rather than leak their details.
- Current frontend's optimistic list is scoped but can be stale: the service is authoritative.

## Verification to run in user's environment

1. `composer install` with configured PHP extensions; `npm ci` with native dependencies.
2. Use disposable MySQL 8 database and run additive `php artisan migrate` (never migrate:fresh production).
3. `php artisan test --testsuite=Unit,Feature,Integration`; include `tests/Integration/Content/PublicationMaintenanceRepositoryTest.php` and authorization tests.
4. `node --test tests/Frontend/publication-plan-maintenance.test.mjs`; `npm run build`; browser test multi-tab version conflict, 50-ID bulk cancellation, permission and cross-brand isolation.
5. Verify Kinship catalogue sync, Storage media inspection, account connections, OAuth redirect and permission restrictions.

## Still open; not a completed publishing engine

Provider live credentials/API review, platform-specific media upload and post-status adapters, dispatch gating, real durable remote worker, retries based on provider idempotency guarantees, reconciliation with provider receipts, observability, live provider contract tests, MySQL concurrency and browser E2E. Keep live posting flag disabled. Pensuh.com privacy/legal changes still not deployed.
