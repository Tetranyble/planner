# Increment 58 — YouTube resumable transfer, verified Storage picker, and AI editorial claim inspection

Date: 2026-11-15. Baseline: complete Increment 57 project archive. This is source implementation, **not a production release**. Supersedes older statements that no YouTube resumable worker exists.

## Scope and source entry points

### Publishing engine: durable YouTube resumable video stages

- `app/Domain/Publishing/YouTube/ResumableUploadProtocol.php`: strict pinned HTTPS Google upload session validation, byte ranges/chunks (8 MiB max per stage), 308 committed-range offset parsing, incomplete/ambiguous responses and exact video/channel confirmation. Never guess offset and resend blindly.
- `CanonicalVideoChunkSourceContract` and its default `UnavailableVideoChunkSource` implementation: Tetranyble Storage remains the **sole** physical-media owner; a real audited, range-readable byte source must be verified against the installed package before delivery can be released. The default adapter blocks all upload execution.
- `LaravelYouTubeUploadTransport` with fixed Google API endpoints, no redirects or automatic HTTP retries, and bounded timeouts. The transfer session URL and the video ID are encrypted at rest in the Eloquent transfer model; credentials remain in the existing encrypted provider vault and are neither persisted in transfer attempts nor surfaced in API/React.
- New additive `database/migrations/2026_11_15_110000_create_youtube_resumable_transfers.php`: scoped `youtube_resumable_transfers`, append-only `youtube_resumable_attempts`, `youtube_resumable_events` tables with tenant foreign keys, idempotent per-run identity, scheduling index and transactional lease fencing.
- `YouTubeTransferRepositoryContract` / `YouTubeTransferRepository` and `ProcessYouTubeTransfers`: explicit manager arming, transactional stage initiation, provider HTTP I/O outside database transactions, bounded Google offset/status probes, default-disabled production worker, quarantine for unknown outcomes, lease expiry recovery, and independently verified YouTube video/channel confirmation before any publication ledger write. Parent media publication cancellation is prevented once a transfer is actively armed/attempted.
- `ManageYouTubeTransfers`, `YouTubeTransferController`, `ArmYouTubeTransferController`, `YouTubeTransferReleaseController` and scheduled CLI commands (`planner:process-youtube-transfers`, `planner:recover-youtube-transfers`). React `MediaPublicationPanel.jsx` shows stages, confirmed byte progress, release blockers and operator controls.

**Source safety boundary**: `PLANNER_MEDIA_WORKER_ENABLED=false`, `PLANNER_MEDIA_DISPATCH_RELEASED=false`, `PLANNER_YOUTUBE_RESUMABLE_ENABLED=false`, `PLANNER_YOUTUBE_MEDIA_ENTITLED=false` by default. Setting flags alone cannot make a working upload: `UnavailableVideoChunkSource` still blocks it. Google app approvals, live token scopes, Storage byte streaming, MySQL concurrency and callback/receipt validation remain unverified. No actual media bytes were uploaded. No provider posting was enabled.

### Content operations: Tetranyble Storage candidate picker

`StorageMediaCandidateRepositoryContract` / `StorageMediaCandidateRepository`, `ListStorageMediaCandidates`, API `StorageMediaCandidateController::index` and Content Studio React add a read-only, actor- and workspace-scoped browser of already-uploaded canonical media. Every returned candidate is rechecked through existing Storage inspector policy. This is **not** a new uploader, storage copy, CDN distribution service, or grant to access another user's files. Missing package runtime integration is explicitly a gate.

### AI editorial review: read-only claim flags

`EditorialClaimScanner`, `AiClaimReviewRepositoryContract`, `AiClaimReviewRepository`, `ReviewAiSuggestionClaims` and the invokable API `InspectAiSuggestionClaimsController` add a tenant-scoped, immutable-hash-checked claim-risk preflight for saved AI suggestions. The React AI suggestion panel shows potentially absolute, numeric, financial and regulatory claims and any matching approved wording. This engine is **not a factual verifier**; a matching wording does not establish evidence validity, and all claims still require human review. It cannot create a new draft, approve it, schedule, or publish through the review endpoint.

## Checks actually executed in authoring environment

- `php -l` over all PHP sources/tests: **1,179 passed** at initial sweep (re-run after edits).
- `php scripts/check_repository_boundaries.php`: **48 Eloquent repositories PASS**.
- `php scripts/check_kinship_catalog.php`: **125 models / 907 catalog and special permissions PASS**.
- `php tests/Standalone/youtube_resumable_protocol.php`: **25 assertions PASS**.
- `php tests/Standalone/ai_editorial_claim_scanner.php`: **8 assertions PASS**.
- `node --test tests/Frontend/ai-editorial-claim-review.test.mjs tests/Frontend/youtube-resumable-architecture.test.mjs tests/Frontend/storage-media-browser.test.mjs`: **10/10 PASS**.
- Full Node frontend checks: **the three pre-existing `@babel/parser` missing-module failures remain** (membership, brand membership, onboarding). These need a clean npm install and rerun. No new known frontend failures.
- Authored but **NOT executed**: `tests/Unit/Publishing/YouTubeResumableProtocolTest.php` and `tests/Integration/Content/YouTubeResumableDurabilityTest.php`. No real MySQL 8, vendor dependencies, PHPUnit, Kinship runtime or real Google OAuth/provider tests ran. Do **not** mark runtime or live upload verified.

## Local release and testing sequence (disposable database first)

1. Install locked Composer dependencies and required PHP extensions (pdo_mysql, mbstring, dom, xmlwriter, intl). Use `composer install` and `npm ci`; do not reuse incompatible node_modules bundles.
2. Configure a disposable MySQL 8 database and correct Kinship, organisation context and Storage package settings; run ordinary additive `php artisan migrate` on existing databases. On a disposable test database only, `php artisan migrate:fresh` may be used. Inspect/rollback the three new tables.
3. Run `php artisan test --testsuite=Unit`, `php artisan test --testsuite=Feature`, `php artisan test --testsuite=Integration`, full frontend tests, `npm run build` and browser E2E. Verify storage-media isolation and stale approval/role refusal.
4. Concurrently arm the same transfer, race two worker claims, expire a lease in each stage, simulate 308 offset, 503/timeout/410, mismatched channel, replay of already-sent INIT and encrypted session storage. Assert no duplicate YouTube publish, no skipped byte offset, no unauthorized provider egress, no false published_posts rows.
5. **Only after** verifying a real range-capable Tetranyble Storage adapter and explicitly approved provider apps/scopes, use a controlled test channel to validate Google initiation, upload offsets, processing verification, and parent-job/published-post ledger integrity. Retain all release switches OFF until reviewed evidence is recorded.
6. Pensuh.com privacy/terms/cookies/data-deletion public pages remain a separate tracked **NOT DEPLOYED** compliance workstream. Do not mark them deployed from this archive.

## Updated five-batch source-completion estimates

| Batch | Baseline | Increment 58 | Status |
|---|---:|---:|---|
| 1 Publishing Engine | 90% | 95% | IN PROGRESS — verified byte-reader, provider approvals and runtime verification remain |
| 2 Content Operations | 88% | 94% | IN PROGRESS — full browser, verified Storage delivery and runtime remain |
| 3 AI Marketing Intelligence | 62% | 67% | IN PROGRESS — broader intelligence/recommendation and factual evidence remain |
| 4 Analytics & Automation | 60% | 60% | IN PROGRESS — cross-provider analytics and attribution remain |
| 5 Integrations & Production | 50% | 59% | IN PROGRESS — UTCP/MCP, deployment, monitoring, end-to-end security remain |

Estimated unweighted mean **75%** source implementation (+5 points versus Increment 57), **0/5 batches accepted**. Percentages are engineering estimates, not independently measured test coverage or production readiness. The default application never starts a live YouTube transfer with the unverified Storage byte-reader.
