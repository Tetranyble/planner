# Increment 54 — verified provider authorization, Facebook Page text delivery and confirmed-post ledger

**Source baseline:** full Increment 53 (`Planner_Increment53_Integrated_Publishing_Worker_2026-11-14.zip`). **Delivery is source-implemented, runtime-unverified.** The 5 integrated batches remain **NOT ACCEPTED**. All global publishing switches and all new per-provider entitlements default **OFF**. Never enable production publication merely to pass a test.

## Connected implementation (not isolated scaffolding)

1. **Actual publishing consent at OAuth:** optional, per-provider permission requests gated by separately attested app-entitlement flags. Facebook Pages requests `pages_manage_posts` alongside `pages_show_list`/`pages_read_engagement`; X requests `tweet.write`; LinkedIn requests `w_member_social`. Reconnect existing accounts to obtain new scopes; old grants do not inherit permissions.
2. **Exact-account entitlement verification:** new `VerifySocialPublishingCapability` service, `SocialPublishCapabilityRepositoryContract` + scoped Eloquent implementation, REST and Inertia invokable controllers, operator button, Kinship manager permissions and audit event. Verifier obtains OAuth credential and checks scopes, performs only read-only provider calls against fixed HTTPS hosts, verifies the current user or Page ID, and persists a 30-minute `AUTOMATIC` capability only if the encrypted credential and connection still match. No bearer token/Page token reaches browser/API JSON.
3. **Facebook Page token resolution:** `MetaPageTokenResolver` uses Graph `/me/accounts` with bounded cursor pagination, verifies the selected Page ID, required Page task (`CREATE_CONTENT` or `MANAGE`) and gets a Page access token **in process only**. No arbitrary provider-supplied URL, no redirects, no token persistence. Called before the attempt begins and again as part of the read-only verification process. Facebook Page text joins LinkedIn/X text in the source-level worker allowlist; app review and live tests still required.
4. **Dispatch-time entitlement renewal:** just before any `STARTED` attempt, the worker rechecks the original arming actor's current organisation and Kinship permissions and probes provider identity/scope again; a failing check returns to manual hold with a redacted blocker, **without remote POST**. The existing lease, exact approval hash, version, UTC due time and kill switches stay in place.
5. **Confirmed-post persistence:** successful provider receipts now create a `published_posts` row in the **same** transaction as the `publication_attempts` and `publication_jobs` final status. Receipt collision across jobs produces `UNCERTAIN` requiring reconciliation. This ledger is reflected in the operations API and React history, rather than inferring delivery from sandbox or audit event counts.
6. **Media editorial preflight:** six-platform metadata restrictions and explicit media-required checks for Instagram/TikTok/YouTube return stable blocker codes through the existing readiness API. Canonical Tetranyble Storage safety and SHA-256 checks are still required separately. Metadata validation **does not** authorize an image/video upload.
7. **Safe operational audit:** `planner:audit-publication-release` now provides `--json` and `--strict` modes with sanitized per-provider entitlement gates and no live API calls or exposed secrets.

## Environment configuration (defaults are all false)

```env
PLANNER_TEXT_DISPATCH_ENABLED=false
PLANNER_TEXT_DISPATCH_PROVIDER_APPROVED=false
PLANNER_TEXT_DISPATCH_RELEASED=false
PLANNER_FACEBOOK_PUBLISHING_ENTITLED=false
PLANNER_LINKEDIN_PUBLISHING_ENTITLED=false
PLANNER_X_PUBLISHING_ENTITLED=false
```

Entitlement flags are attestation that the appropriate platform product/app review and billing access have been independently verified. They **do not themselves constitute** authority to publish. Existing OAuth accounts must explicitly reconnect to request upgraded scopes; verify on the social accounts screen. Authorization to create/arm an `AUTOMATIC` target still requires a current brand-specific permission, exact-version content approval and manual owner/manager arming. The worker is a no-op unless all global release flags are true **and** Laravel runs in production. Test HTTP via `Http::fake()` and sandbox grants before any real release.

## Verification actually executed here

- `php tests/Standalone/publishing_scope_and_media.php` — **15 PASS**, no dependencies/database/network.
- `php tests/Standalone/provider_text_contracts.php` — **112 PASS**, no network.
- `php tests/Standalone/guarded_text_dispatch.php` — **22 PASS**, no network.
- `node --test tests/Frontend/publishing-entitlements-ledger.test.mjs` — **5/5 PASS** (source-level safety tests).
- `node --test tests/Frontend/*.test.mjs` — **126/129 PASS** before the new five checks, **131/134 PASS** after (three existing `@babel/parser` module-load errors from absent node_modules). 
- PHP syntax across project, Kinship catalog and repository boundary checks: PASS (exact final counts in handoff).
- **NOT RUN:** Laravel PHPUnit, disposable MySQL migrations / row-lock races, `Http::fake` feature cases inside framework, `npm ci && npm run build`, browser E2E or any real Facebook/LinkedIn/X API request.

### Tests added (source authored; local execution required)

- `tests/Unit/Publishing/PublishingScopePolicyTest.php` — explicit scopes/kind and LinkedIn author identity.
- `tests/Unit/Content/PlatformMediaPreflightRulesTest.php` — safe format/count metadata.
- `tests/Feature/MetaPageTokenResolverTest.php` — fake Graph Page role/ciphertext/no-network access.
- `tests/Integration/Publishing/PublishCapabilityEvidenceTest.php` — grant rotation fencing, short-lived capability record.
- `tests/Integration/Publishing/ConfirmedPublicationLedgerTest.php` — confirmed receipts and collision quarantine.
- `tests/Frontend/publishing-entitlements-ledger.test.mjs` — UI/authorization/persistence source guards.

## Local verification and release gate (user-owned)

1. Install required PHP 8.4 extensions + Composer packages, Node dependencies, disposable MySQL 8, current Kinship + Tetranyble Storage configuration. **Do not use migrate:fresh on existing data.**
2. Keep all six switches OFF. In disposable test DB run `php artisan migrate`, `php artisan test --testsuite=Unit`, `--testsuite=Feature`, and `--testsuite=Integration`. Check Facebook Page ID scoping, token rotations during probe, provider 3xx/429/timeout/malformed responses, duplicate receipt collisions and concurrent arm/claim/finalization.
3. `npm ci && npm run build`, `node --test tests/Frontend/*.test.mjs`, then browser checks for social-account verification, approval, held plan, arm and operations history.
4. `php artisan planner:audit-publication-release --json`; only after provider app review and sandbox consent may entitlement flags be set on a safe staging environment. Run `--strict` **only in release validation**; it is expected to fail with global switches off.
5. Test the Facebook Page role/token exchange against a real managed sandbox Page and verify a real confirmed receipt. Separately verify X and LinkedIn author IDs, OAuth product entitlements, billing/rate limits and response semantics. No live vendor tests executed here.
6. Meta data-deletion callback, retention review and **Pensuh.com legal/privacy/data-deletion policy pages** remain open and **not deployed**. Release requires documented privacy review.

## Known important limitations

- Live delivery for Instagram/TikTok/YouTube and all image/video uploads **still not implemented**; media metadata preflight is not media transport.
- LinkedIn API author identity and provider return semantics require real sandbox verification. User profile IDs may be opaque; compiler validates fixed `urn:li:person:{member-id}` form without guessing conversions. LinkedIn Company Page posting is still not enabled.
- OAuth renewal and account capability checks may expire before schedule time; worker rechecks at dispatch and falls back to manual hold when verification fails.
- HTTP timeouts and uncertain provider responses never cause automatic replay; authenticated human reconciliation is still needed.
- Full runtime tests, provider entitlements, platform terms and production release controls remain mandatory. These gaps keep **all five batches open**.

## Source-coverage estimate (heuristic, not production readiness)

| Batch | 53 baseline | 54 estimate | Status |
|---|---:|---:|---|
| 1 Publishing Engine | 61% | **72%** | IN PROGRESS — three text-provider paths, no media or proven live provider integration |
| 2 Content Operations | 70% | **77%** | IN PROGRESS — media validation/approval integrated, no live media publishing |
| 3 AI Marketing Intelligence | 50% | **50%** | IN PROGRESS |
| 4 Analytics & Automation | 45% | **45%** | IN PROGRESS |
| 5 Integrations & Production | 25% | **31%** | IN PROGRESS — security, attestation, release audit and tests; runtime gates open |

**Overall estimated source coverage: 55%** (unweighted mean of 72, 77, 50, 45, 31), up 5 percentage points from Increment 53's ~50%. **Full batches accepted: 0/5.** Percentages reflect engineering judgment, not measured production readiness.

## Current reference docs

- Meta Page Feed: https://developers.facebook.com/docs/graph-api/reference/page/feed/
- LinkedIn Posts API: https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api
- TikTok Direct Post: https://developers.tiktok.com/docs/en/content-posting-api-reference-direct-post
- YouTube videos.insert: https://developers.google.com/youtube/data/reference/rest/v3/videos/insert

Provider docs and terms evolve. Reconfirm at launch, especially API versions, app approval, rate limits and identity fields.
