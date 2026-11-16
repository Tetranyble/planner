# Increment 53 — Connected, lease-fenced publication execution (source milestone)

**Source baseline:** Increment 52. **Product goal:** close the operational gap between held publication plans and a real, auditable, opt-in text publishing worker. All five batches remain **NOT ACCEPTED** until the full acceptance suites run and missing provider features are implemented.

## Implemented together

1. **Explicit automatic-target creation:** `publishing_mode=AUTOMATIC` is accepted for fresh text targets only with manager authorisation, currently healthy provider connection, current verified automatic capability and the *three* production execution switches. Legacy ASSISTED target hashes are unchanged; an AUTOMATIC target has a new payload digest and requires its own independent approval.
2. **Operator arm:** `POST .../publication-intents/{jobId}/arm` requires Kinship manager/owner role, `approval_request.update`, an explicit live-delivery acknowledgement, exact job version, full current approval/media/policy checks and an eligible provider. Never arms already-attempted jobs.
3. **Due worker and fencing:** `planner:process-publication-execution` is registered with the minute scheduler. Its default is a no-op. Claim uses a transaction and row lock; only one worker wins. It reconstitutes an active organisation context and verifies the original arming user's **current** rights at dispatch time.
4. **Persisted attempt-before-send:** an immutable operation key is unique per organisation; STARTED attempt and PUBLISHING state commit before any HTTP request. The network call happens outside the transaction. Completion is accepted only under the matching live lease token and operation key.
5. **Receipt and failure outcomes:** verified provider receipts produce a PUBLISHED record; known rejections produce FAILED; retryable, rate-limited or ambiguous responses produce UNCERTAIN requiring human reconciliation. No automatic replays. Lease recovery remains conservative. Response and event payloads are sanitized and model casts now encode JSON correctly.
6. **Pre-dispatch cancellation:** Operators can cancel both manually held and armed-but-not-yet-claimed scheduled plans, including atomic bulk cancellation. In-flight or previously attempted jobs cannot be cancelled by the planner endpoint.
7. **Production release and operator visibility:** `config/planner.php` now requires `PLANNER_TEXT_DISPATCH_ENABLED`, `PLANNER_TEXT_DISPATCH_PROVIDER_APPROVED`, and `PLANNER_TEXT_DISPATCH_RELEASED`, **all true**, while `APP_ENV=production`. Owner/manager desk displays release state, permits an explicit arm when released, and shows audit history. `planner:audit-publication-release` prints sanitized release prerequisites with no provider request.

**Implemented live text path:** LinkedIn and X only (actual network calls possible *only* with all operator, entitlement, credential, permission and release conditions satisfied). Existing Facebook Page request compiler remains offline because user grant ciphertext does not prove the availability of a Page access token. Facebook, Instagram, TikTok, YouTube and all media delivery are **not implemented**. External confirmations, provider API review and live compatibility are NOT tested.

## Pre-deployment local test checklist

Run on disposable MySQL 8 with installed Composer dependencies, configured Kinship and Tetranyble Storage, a safe sandbox provider account and no real customers:

```
php artisan migrate --force
php artisan route:list --path=publication-intents
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
php artisan planner:audit-publication-release
php artisan planner:process-publication-execution --limit=10
npm ci && npm run build
node --test tests/Frontend/*.test.mjs
```

The migration adds `publication_jobs.armed_by_user_id`, `armed_at` and a due index, plus a unique index for `(organisation_id,operation_key)` on attempts. **Preflight existing attempt operation keys for duplicates** before migrating; do not run `migrate:fresh` on an existing database. Use `migrate:fresh` only on a disposable test database.

Release sequence: keep all three flags off; run schema/permission/queue/concurrency tests; verify exact LinkedIn/X app entitlements and scopes; use mock HTTP/credential fixtures; run limited real sandbox posting and verify receipt semantics; inspect duplicate and ambiguous-response recovery; review production legal/privacy controls; authorize a separately auditable release of all three flags. **Never enable live delivery just to pass a test.** Meta deletion callback and pensuh.com legal/privacy updates remain open.

## Verification executed in the build environment

- PHP lint: 1,042 PHP files PASS.
- Repository boundary check: 39 Eloquent repositories PASS.
- Kinship catalogue guard PASS.
- New frontend architecture checks: 6/6 PASS.
- Full frontend suite 126/129 PASS; three existing tests cannot resolve `@babel/parser` because node_modules is absent.
- PHPUnit, MySQL migrations/concurrency, live credentials, Vite and providers: **NOT RUN**.

## Progress estimate (source-coverage heuristic, not production readiness)

Batch 1: **61%** (previous 46%; now connected text dispatch, but most providers/media unimplemented).
Batch 2: **70%** (previous 65%; conditional automatic-target workflow and operator desk).
Batch 3: **50%** (unchanged).
Batch 4: **45%** (unchanged).
Batch 5: **25%** (previous 20%; production release controls and audit/test coverage).
Unweighted total: **50.2% (~50%)**, prior 45.2%. **Development complete: 0/5 batches**. These figures are engineering estimates and may be revised after runtime results.
