# Increment 56 — Staged media execution, verified receipt ledger, and AI campaign editorial conversion

Date: 2026-11-15. Source authority: `Planner_Increment56_Staged_Media_Execution_2026-11-15.zip`, derived from the full Increment 55 archive. **Source changes are implemented; full Laravel/MySQL/Storage/Kinship/live-provider integration has NOT been executed in this environment.** This is not a production publishing release.

## Feature delivery (connected vertical slices)

### 1. Durable multi-stage media execution

- Additive tenant-scoped MySQL migrations: `2026_11_15_091000_create_media_publication_runs.php` and `2026_11_15_092000_create_media_tiktok_consents.php`.
- Dedicated Eloquent repository/contracts, factory-backed source tests, audit evidence, encrypted remote operation refs, fixed stage plan and at-most-once mutating stage start. Lease owner is a 192-bit random token, stage attempts are fenced, read-only status polling is bounded to 12 attempts, and expired in-flight leases are quarantined as **UNCERTAIN**.
- Safe worker CLI `php artisan planner:process-media-publications`; recovery CLI `php artisan planner:recover-media-publication-leases`; scheduler hooks. No remote calls in transactions. Worker disabled unless both `planner.media_worker_enabled` and `planner.media_dispatch_released` are explicitly enabled in production.
- Instagram staged operations: create container, poll processing, finalize, **independently verify media ID and owner**. TikTok: bind freshly queried creator options and explicit per-video privacy/AIGC consent to the approved snapshot; initialize via authenticated PULL_FROM_URL; bounded publish-status reads and independently verify public post ID. Read-only TikTok status probes can continue beyond the short initial creator-options freshness window, while mutating INIT requires freshness. No duplicate sends after ambiguity.
- YouTube resumable upload is **NOT released or implemented end-to-end**; a verified Tetranyble Storage byte stream, 308 offset recovery and live provider validation remain prerequisites.
- Real-origin media distribution uses `MediaDistributionUrlResolverContract`, default-bound to `UnavailableMediaDistributionUrlResolver` which rejects use. The existing Tetranyble Storage package must provide a reviewed implementation. Never substitute arbitrary URLs, public bucket permissions, or unverified third-party storage clients.
- Instagram/TikTok stage HTTP transport has strict exact-host/path/method pinning, rejects redirects, sends bearer tokens only in transport memory, and stores only sanitized typed outcomes. Non-2xx or uncertain network responses never trigger automatic replay.

### 2. Verified publication and two-person reconciliation

- A second provider read must return the **same** receipt before the run can reach COMPLETED; for Instagram the media `owner.id` must equal the connected professional account ID. The final database transaction must atomically write `publication_attempts`, `published_posts`, `publication_jobs=PUBLISHED` and `publication_events`; any inconsistency rolls back.
- For permanent failure or an uncertain/missing provider result, the run, parent publication job and a redacted publication attempt are terminally recorded together. UNCERTAIN parent jobs can reuse the existing independent `PublicationReconciliationRepository` desk. **Human decisions never turn an uncertain provider state into an automatically published post.**
- Reschedule, cancel and bulk cancellation are blocked while a media run exists except for a never-attempted, explicitly cancelled run.
- API: tenant-scoped media run list, detail, creation, operator arm/cancel, TikTok creator options and per-post consent. React content studio provides stage history, safety states, and required consent inputs.

### 3. AI campaign proposal → explicit editorial draft

- Additive `2026_11_15_093000_create_ai_campaign_proposal_drafts.php`, composite tenant/brand foreign keys and unique proposal→draft provenance; Eloquent model/factory and existing repository contract extended.
- `ManageCampaignProposals::createEditorialDraft` verifies **independent campaign approval and immutable proposal hash**, locks organisation and brand, and creates exactly one editor-written `TEXT` draft with `ai_generated=true`, `includes_sensitive_claim=null` (unknown). The original approved proposal hash is stored in a unique, brand-scoped provenance link; retries return the previously created draft.
- `POST /api/v1/organisations/{organisation}/brands/{brand}/content/ai-campaign-proposals/{proposalUuid}/draft` requires Kinship `content_item.store` and explicit editor copy. React campaign panel exposes the conversion only for APPROVED proposals.
- Strategy approval does **not** approve any content version, create a social target, schedule work, or publish.

## Safety and architecture

- All business reads and writes are tenant- and brand-scoped. Kinship route permissions are rechecked by brand services. Commands are invokable controllers; persistence stays under `App/Repository/Contracts` and `App/Repository/Eloquent`.
- No provider credentials, signed media URLs, or remote raw bodies in model resources, events, CLI output, or audit evidence.
- All new migrations are additive. **Never run `migrate:fresh` on an existing database**. Use disposable MySQL 8 for testing only.
- Pensuh.com privacy, data-deletion, lead consent and related legal-page updates remain tracked **NOT DEPLOYED**.

## Executed evidence in this environment

- PHP syntax: **1,115 PHP source/test files passed** (`php -l` on application, database, routes, tests).
- `php scripts/check_repository_boundaries.php`: PASS, 43 Eloquent repositories.
- `php scripts/check_kinship_catalog.php`: PASS, 119 model files and 865 discovered/special permission entries.
- `php tests/Standalone/media_stage_transitions.php`: **55 assertions PASS**.
- `php scripts/check_media_publication_protocols.php`: **54 assertions PASS** (legacy release assertions updated for staged worker).
- `node --test tests/Frontend/*.test.mjs`: **150/153 pass**, the three pre-existing startup/onboarding/membership parser tests fail because `@babel/parser` is unavailable here. `node --test tests/Frontend/ai-campaign-editorial-draft.test.mjs tests/Frontend/media-stage-execution.test.mjs tests/Frontend/media-publication-preparation.test.mjs`: **19/19 PASS**.
- PHP integration/unit test files are authored, but **PHPUnit, migrations, MySQL lock racing, Kinship runtime, Tetranyble Storage runtime, production Vite build, browser E2E, and actual provider calls were NOT executed**.

## Run locally (do not run against production)

1. Create separate `.env.testing` and disposable **MySQL 8** database, with PHP extensions `pdo_mysql`, `dom`, `mbstring`, `intl`, `xml`/`xmlwriter` installed; inspect vendor requirements in `composer.lock`.
2. Run `composer install --no-interaction`, `npm ci`, `php artisan migrate --env=testing`, `php artisan route:list`, `php artisan kinship:seed --help` (verify actual installed package options), `php artisan test --testsuite=Unit`, `php artisan test --testsuite=Feature`, `php artisan test --testsuite=Integration` and `npm run build` against the disposable environment. Inspect and reconcile pre-existing baseline failures; record stdout/exit status and commit identity.
3. Specifically execute `tests/Integration/Content/MediaPublicationRunDurabilityTest.php`, `tests/Integration/Ai/AiCampaignProposalDraftRepositoryTest.php`, and `tests/Unit/Publishing/MediaStageTransitionTest.php`. Exercise concurrent worker claims and expired leases against actual InnoDB locks, and test 10-minute TikTok consent, rotated token invalidation, duplicate remote receipts, wrong Instagram media owner and same-job concurrent writes.
4. Verify Tetranyble Storage canonical byte integrity, access control, signed-origin expiry/allowlist, and cache behavior; build and test an approved implementation of `MediaDistributionUrlResolverContract` before any release flag changes.
5. Obtain provider app review and sandbox accounts; validate grant scopes, TikTok creator-info/privacy behavior, Instagram container lifecycle and media owner read, provider receipts, failure/resume behavior. **Do not enable publishing until every gate passes.** YouTube additionally needs resumable stream implementation and 308 recovery; no release from config alone.
6. Verify UI with light/dark theme, editor role switching, cross-tenant forged IDs, cancellation/arming races, publication reconciliation evidence, idempotent AI draft conversion, and compliance links.

## Five-batch estimated SOURCE implementation coverage

| Batch | Previous | Current estimate | Status |
|---|---:|---:|---|
| 1. Publishing Engine | 79% | **90%** | OPEN — trusted media Storage delivery, YouTube upload, provider approvals/live validation remain |
| 2. Content Operations | 81% | **87%** | OPEN — real media picker/upload browser QA and complete scheduling flow remain |
| 3. AI Marketing Intelligence | 50% | **60%** | OPEN — AI evaluation, claim provenance/optimization and deeper campaign controls remain |
| 4. Analytics & Automation | 45% | **45%** | OPEN — provider analytics ingestion, attribution and wider triggers remain |
| 5. Integrations & Production | 34% | **43%** | OPEN — UTCP/MCP, CI/MySQL, Storage integration, security, deployment and compliance remain |

**Overall estimated source coverage: 65%**, the arithmetic mean of the five current subjective estimates (90+87+60+45+43)/5. These percentages are not runtime pass rates and are not an acceptance decision. **0/5 batches development-complete/accepted**. All release flags remain OFF.
