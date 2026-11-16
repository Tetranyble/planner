# Increment 55 — three-platform media protocols, durable preparation desk and guarded egress

**Source baseline:** `Planner_Increment54_Verified_Provider_Publishing_2026-11-14.zip`. **Source-implemented; Laravel/MySQL and provider-runtime UNVERIFIED.** No provider upload/post is executed by the new preparation workflow. Keep all production release switches OFF. Do not confuse a prepared media workflow with an authorized publication.

## Connected source changes

1. **Provider-specific stages:** `app/Domain/Publishing/Media/MediaPublicationProtocols.php` describes Instagram single JPEG post and MP4 Reel container creation/status/publish, TikTok Direct Post video pull with current creator options and explicit privacy consent, and YouTube resumable upload initialization and local Storage streaming requirements. It validates MIME/count/bytes conservatively, uses allowlisted HTTPS distribution origins, and interprets provider receipts without treating missing post IDs as confirmed posts. Instagram and TikTok status requests are bounded, typed offline contracts. Official documentation to recheck before production: [Instagram platform](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-facebook-login/content-publishing/), [TikTok Direct Post](https://developers.tiktok.com/docs/en/content-posting-api-reference-direct-post), [TikTok status](https://developers.tiktok.com/docs/en/content-posting-api-reference-get-video-status), [YouTube resumable protocol](https://developers.google.com/youtube/data/guides/using_resumable_upload_protocol).
2. **Durable preparation records:** two additive MySQL tables `media_publication_preparations` and `media_publication_preparation_events`, unique job-level idempotency, versioned abort, FK-complete organisation/brand scope and append-only audit evidence. These records are explicitly separate from `publication_jobs`, `publication_attempts` and `published_posts`; they are not successful delivery claims.
3. **Canonical integrity and current approval:** `PrepareMediaPublications` checks user/brand Kinship permissions, held-and-unattempted job, target ownership, exactly one media link, Tetranyble Storage READY/virus trust, SHA-256/byte-size/MIME equality, active approval policy/version/hash/quorum, and a supported provider. Automatic provider capability and dispatch remain blockers. A replay with the same approval+media is idempotent; changed evidence is rejected. No URL, token or provider request body is stored.
4. **REST + React:** brand-scoped `GET/POST /content/media-publication-preparations`, `GET /{preparationId}`, and `POST /{preparationId}/abort`, plus a read-only media release report. Operators can prepare one held plan, inspect platform stages and the preparation timeline, and abort before remote operations. Frontend explicitly labels posting disabled.
5. **Guarded future egress boundary:** `MediaStepRequest`, exact host/path/query rules, `GuardedMediaStep`, `MediaStepAuthorization`, mockable transport, and a production-gated Laravel transport. Signed origin URLs must be obtained ephemerally from verified Storage distribution; they never belong in evidence tables, frontend payloads or logs. Current code does NOT have a verified Storage distribution URL resolver, a live stage-fenced media dispatcher, TikTok creator-info workflow or YouTube resumable byte-stream uploader. YouTube initiation remains hard blocked even with release flags until that driver exists. No registered live media worker calls the new transport.
6. **Release audit:** read-only `GET /content/media-publication-release` shows missing provider app review, media delivery origin and worker registration. The media app-review/distribution/worker switches are OFF by default in `config/planner.php`, and a permanent `LIVE_MEDIA_EXECUTION_NOT_IMPLEMENTED` blocker exists until a full verified worker is implemented.

## Exact local verification and results

**Executed here:**
- `php scripts/check_media_publication_protocols.php` — **53 assertions passed** (pure PHP; no requests).
- `node --test tests/Frontend/media-publication-preparation.test.mjs` — **7/7 passed**.
- `node --test tests/Frontend/*.test.mjs` — **138/141 PASS**, with three existing `@babel/parser` missing-dependency errors (no node_modules).
- `npm run test:controllers` — 4/4 passed.
- PHP syntax lint **1,078 files PASS**; repository guard **41 Eloquent implementations PASS**; Kinship catalogue guard PASS; `npm run test:controllers` **4/4 PASS**.

**Authored but NOT executed:** `tests/Unit/Publishing/MediaPublicationProtocolsTest.php`, `tests/Unit/Publishing/MediaStepEgressPolicyTest.php`, `tests/Feature/GuardedMediaStepTest.php`, `tests/Integration/Content/MediaPublicationPreparationPersistenceTest.php`. The archive has no `vendor/`; required extensions such as `pdo_mysql`, `intl`, `mbstring` and `dom` are unavailable here. There was no MySQL DB, framework bootstrap or real remote provider test. Install dependencies on the user's system, run additive `php artisan migrate` against disposable MySQL 8 (never `migrate:fresh` against real existing data), run targeted PHPUnit, `npm ci`, frontend suite and Vite build, and test strict tenant, Kinship and media hash conditions.

## Unfinished production functionality

- **Instagram:** app-review permissions, publicly accessible verified Storage distribution, durable multi-request container creation/poll/publish execution, reconciliation for network ambiguity, callback receipt verification, content and rate-limit validation.
- **TikTok:** `video.publish` app audit, creator-info privacy choices and explicit per-post user consent, verified URL pull or trusted file chunk upload, asynchronous status and failure/moderation reconciliation, unreviewed app private-only restrictions.
- **YouTube:** verified `youtube.upload` scope, exact-channel credential binding, resumable session token persistence with encrypted handling, byte streaming from Tetranyble Storage with continuation offsets and safe 308 processing, quota/error handling, post confirmation.
- No automatic retries following a send that might have reached a provider. The existing text publishing worker remains unchanged and disabled by default. Pensuh.com privacy/legal updates remain tracked and **not deployed**.

## Batch acceptance — estimated SOURCE coverage, not runtime confidence

| Batch | Prior | After 55 | Acceptance |
|---|---:|---:|---|
| 1 Publishing Engine | 72% | **79%** | OPEN — media stage transport not integrated with real worker |
| 2 Content Operations | 77% | **81%** | OPEN — staged media desk, no completed media scheduling |
| 3 AI Marketing Intelligence | 50% | **50%** | OPEN |
| 4 Analytics & Automation | 45% | **45%** | OPEN |
| 5 Integrations & Production | 31% | **34%** | OPEN — scoped APIs, schema, security boundaries; runtime gates open |

Unweighted estimated overall source implementation: **58%** (previous 55%, rounded). **Development-complete batches: 0/5.** Do not promote to 60% merely to meet the targeted five-point delivery increment. Next connected scope should finish verified canonical media delivery adapters, stage-fenced asynchronous provider execution and real provider confirmation, plus end-to-end MySQL/HTTP mock testing.
