# Increment 33 — Version-bound content review and progress assessment

**Baseline:** `Planner_Phase4_Content_Media_Studio_2026-11-14.zip` (full project from increment 32).  The authoritative next artifact is `Planner_Phase4_Content_Approval_Workflows_2026-11-14.zip`.

## Delivered source functionality

- Added `ContentApprovalRepositoryContract` and `ContentApprovalRepository` with tenant-and-brand scoped target/request lookups, policy/rule selection, decision persistence and a pending request uniqueness guard. `AppServiceProvider` binds the contract.
- `ManageContentApprovals` enforces Kinship + active brand membership on both API and Inertia callers, and serializes edits/reviews with organisation-then-brand locks. Reviewers are checked both at route edge and in the service.
- Submission requires an ACTIVE brand approval policy, ACTIVE social account, latest content version, caller-supplied expected target hash, and fresh canonical media safety + SHA-256. It records a deterministic, immutable hash of: content item/version, target payload, account/provider, media digests, active policy version, and applicable rule requirements and reviewer roles.
- Review re-hashes the live immutable target and active policy, re-inspects canonical media, checks the live reviewer role against every applicable rule, and enforces a unique reviewer decision and required quorum. Self-review is blocked whenever the policy/rule says so. Reviewer comments are mandatory. REJECT is terminal. Approvals need independent distinct reviewers when quorum >1.
- The current content schema does not yet expose reliable `ai_generated` and `includes_sensitive_claim` booleans; all publish rules matching platform and content kind contribute the **maximum** review quorum and strictest self-approval/role restrictions. This is intentionally conservative; do not weaken by pretending unknown flags are false. Rules with `requires_human_approval=false` cannot bypass the minimum one-human-review policy in this increment.
- Target and version payload hashes are not editable, and approval decisions are append-only. A new content version withdraws all pending reviews associated with the edited item inside the same database transaction. **Previously approved requests remain historical records and do NOT authorize publishing a newer revision**; every future scheduling/publishing operation MUST independently check current content version, active policy, approved request snapshot, media safety, account capabilities and credential health.
- Requests expire after seven days, including an explicit `planner:expire-content-approvals --limit=100` scheduled every 10 minutes. The worker does not post content or make provider API calls.
- API and web CRUD `index/store` controllers for approval requests and `store` for decisions; `__invoke` for withdrawal, matching the approved controller convention. New Inertia review actions are shown on the Content Studio detail page.
- Factory-backed unit/feature/integration test sources, opt-in local/testing seeder and Node architecture checks. The original physical Storage objects and sample assets remain package-owned; this increment adds no uploader and no social publishing.

## Database change

One additive MySQL 8 migration, `2026_11_14_230000_pending_content_approval_uniqueness.php`, adds a nullable STORED generated `pending_target_marker` column and unique index on `(organisation_id, brand_id, pending_target_marker)`. MySQL allows multiple NULL values, hence non-PENDING historical rows coexist while only one PENDING request exists per target. **Migration aborts with an explicit message when pre-existing duplicate PENDING rows are found.** Review and resolve such rows manually before applying; do not run `migrate:fresh` on a real tenant database.

## API + Web routes

All routes are under `.../organisations/{organisation:uuid}/brands/{brand:uuid}/content` with the normal `auth`/Sanctum, `verified`, `organisation.context` and Kinship gates:

- `GET /{itemUuid}/approvals` — list approval history, `approval_request.index`.
- `POST /{itemUuid}/targets/{targetUuid}/approvals` — submit exact hash `{ expected_target_hash }`, `approval_request.store`.
- `POST /{itemUuid}/approvals/{requestId}/decisions` — `{ decision: APPROVE|REJECT, comment }`, `content.approve`, restricted to manager/owner.
- `POST /{itemUuid}/approvals/{requestId}/withdraw` — original submitter only, `approval_request.update`, invokable controller.

These routes **never** post, schedule, or queue social-media content.

## Tests actually executed in this container

- `php -l` over application, migration, route and test PHP: 876 files, **passed** (pre-final check).
- `node --test tests/Frontend/*.test.mjs`: 75/75 assertions **passed** (pre-final check); JSX for both new screens parsed with the existing Babel parser.
- `php scripts/check_repository_boundaries.php`: passed; `php scripts/verify_brand_scope.php`: 10 assertions passed; test fixture and Kinship catalogue guard checks passed.
- PHPUnit feature/unit/integration tests **authored, NOT executed**. Real MySQL migrations, Kinship runtime grants, locks/races, Tetranyble Storage and provider/OAuth live tests **NOT executed**. Vite production build is not certified.

## Local validation sequence (throwaway MySQL first)

```bash
composer install
npm ci
cp .env.testing.example .env.testing
php artisan key:generate --env=testing --force
php scripts/assert_safe_test_database.php
php artisan migrate:fresh --env=testing --force
php artisan kinship:seed --help
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
npm run test:content-approvals
npm run build
php artisan list | grep planner:expire-content-approvals
```

After verifying the test environment, use **additive** `php artisan migrate --force` on the actual database and seed/synchronize Kinship permissions in the correct workspace following the package's documented process.

## Remaining requirements and risks

1. No first-class Tetranyble Storage browser/uploader or rich document/media editing yet; canonical media references only.
2. No scheduling, publishing jobs, provider-side upload, durable outbox executor or reliable idempotency/reconciliation for external posts.
3. No production verification of approval tests, migration/index behavior, role policies or concurrent workers.
4. Existing `approval_requests` table is reused; migrating old duplicate pending requests may require manual reconciliation.
5. Limited `ai_generated` / sensitive-claim flags need explicit typed fields before production-level rule matching.
6. Rules applied now are conservatively aggregated, not advanced priority-based condition precedence; document and test complete semantics before allowing publication.
7. The `content.approve` API records human decisions, **not** a token authorizing provider side effects. A later publish worker must recompute the exact approved snapshot and validate both grant and asset health again.
8. **Pensuh.com** Privacy Policy, Cookie Policy, Terms, data-deletion instructions, forms/consent, Security, Refund Policy and footer/sitemap updates remain a separate documented legal/compliance workstream, NOT DEPLOYED. Track in `docs/compliance/PENSUH_COM_PRIVACY_AND_LEGAL_PAGES_WORKSTREAM.md`.

## Overall progress estimate

This is an estimate of remaining **scope and production-readiness effort**, not count of lines of code or tests passing. The 84-task tracker at this baseline had 54 `NOT_STARTED` tasks, many partially implemented/in-progress tasks and no verified green MySQL gate for this delivery. We estimate **~25–30% project completion, ~70–75% remaining** after this increment. The remaining work is weighted heavily toward scheduled publishing, official provider integration, analytics, AI workflows, leads/UTCP/MCP and security/compliance/runtime release validation. Do not equate source-level code completion with production readiness.
