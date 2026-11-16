# Increment 57 — Provider-sourced YouTube analytics and opt-in automation

**Date:** 2026-11-15. Baseline: complete Increment 56 source ZIP. **Full source implementation; NOT a runtime-verified release.**

## What this increment delivers

1. **Read-only provider analytics ingestion:** `YouTubeVideoStatsClientContract`/`LaravelYouTubeVideoStatsClient` fetch the official YouTube `videos.list` `snippet,statistics` resource via a fixed HTTPS GET to `www.googleapis.com`. Input video IDs are selected from **existing provider-confirmed `published_posts`**, never caller-supplied URLs. Current actor org/brand membership, Kinship permission, channel ID, provider connection ownership, read scopes and unexpired encrypted access token are rechecked at each worker attempt. A response is accepted only if exactly one video matches the expected ID and the returned `snippet.channelId` matches the linked channel. No credentials or raw Google replies are persisted.
2. **Durable bounded execution:** New `provider_metric_sync_runs` and `provider_metric_observations` tables; tenant/brand-composite FKs, unique post/day sync requests, 2-minute leased read attempts, a three-attempt budget, bounded 429/5xx backoff, and recovery for stale GET-only leases. No provider mutation, remote posting, Storage byte reads or credential refresh is triggered. Both queueing and worker execution are disabled by default unless `PLANNER_PROVIDER_METRIC_SYNC_ENABLED=true`.
3. **Opt-in daily automation:** New `provider_metric_sync_policies` with one policy per brand, 0–23 UTC hour, 1–20 confirmed videos/day, author attribution, optimistic concurrency, role-checked creation and pause, a second independent autoqueue kill switch, per-day idempotency, and durable schedule progress. `planner:queue-provider-metrics` only enqueues local GET work. `planner:sync-provider-metrics` performs the read-only fetches. Both scheduled through Laravel Scheduler with `withoutOverlapping/onOneServer`.
4. **Provenance-aware metrics and performance:** The manual `social_metric_snapshots` table stays unchanged and continues to report `USER_REPORTED_UNVERIFIED` data. Provider API observations are separate, preserving null for metrics not supplied by the API. The content dashboard displays provider video views/likes/comments separately; reach, impressions, shares, clicks and ROI are **not** inferred. `ProviderPerformanceSignals` compares matched video cohorts with observations on each side of a 14-day cutoff, with minimum sample size of 3 and explicit truncation handling. This is descriptive inference, **not causality or AI-generated marketing proof**.
5. **Reporting integration:** The existing manual and daily UTC `METRICS_REPORT_ONLY` automations can include **separately labeled** provider API summaries, never combine their views with manual impressions, and never post or mutate content.
6. **Operator controls:** New API routes for listing/queueing provider syncs, schedule GET/PUT, read-only performance comparison; React Content Studio components for selection, status, provenance, daily policy and matched-view signals. All routes retain Kinship edge middleware plus service authorization, and persistence uses repository contracts + Eloquent.

## Important limitations

- **One provider at first:** Only YouTube video statistics are implemented. Facebook/Instagram/LinkedIn/X/TikTok analytics collection remains outstanding; each requires its own reviewed API permissions and changing metric semantics. Do not label video *views* as impressions or estimated reach.
- This does not upload videos to YouTube or change Instagram/TikTok delivery. The Batch 1 **YouTube resumable Storage-stream upload remains outstanding**; provider publishing release switches remain OFF.
- A source-verified HTTP response and matching channel ID are provider API observations, **not independently audited business results**. Missing like/comment data remain `null`. If a period would exceed 500 observations, totals are withheld; matched-trend scan caps at 1,000 observations and returns a truncated status.
- Default flag state is OFF; no provider request should run without deliberate operator configuration and a valid OAuth read grant. A production launch still requires verified MySQL/Kinship runtime and actual Google sandbox responses.
- Published posts must already be provider-confirmed in the `published_posts` ledger. The sync never accepts arbitrary video IDs, does not inspect user uploads, and does not write to publishing state.
- Pensuh.com public privacy, cookies, lead consent, data deletion and other legal updates remain **NOT DEPLOYED**.

## New configuration (set only after testing)

```dotenv
PLANNER_PROVIDER_METRIC_SYNC_ENABLED=false
PLANNER_PROVIDER_METRIC_AUTOQUEUE_ENABLED=false
```

`true` for the first flag allows manually queued, read-only syncs; `true` for the second allows explicit brand daily policies to enqueue work at their configured UTC hour. Both default to `false`. The existing publishing flags remain `false`.

## Routes and commands

The following routes are under `/api/v1/organisations/{organisation:uuid}/brands/{brand:uuid}/content/`:

- `GET /social-metrics` — manual stats plus a **separate** `provider_api` segment.
- `GET/POST /social-metrics/provider-syncs` — list and queue 1–20 confirmed YouTube posts; POST restricted to verified manager/owner with rate limiting and Kinship update permission.
- `GET/PUT /social-metrics/provider-sync-schedule` — scoped, versioned daily policy and release visibility.
- `GET /social-metrics/performance-insights` — same-video read-only historical comparison.
- Existing `GET /report-automations` now exposes provider API summaries in completed runs.

`php artisan planner:queue-provider-metrics --limit=20` and `php artisan planner:sync-provider-metrics --limit=20` are both no-ops when release flags are OFF.

## Actual checks performed in authoring environment

- `php -l` passed for **1,147 PHP files** in `app`, `database`, `routes` and `tests` at the last full sweep.
- `php scripts/check_repository_boundaries.php`: PASS, **45 Eloquent repositories**.
- `php scripts/check_kinship_catalog.php`: PASS, **122 models / 886 permissions and special entries**.
- `php tests/Standalone/youtube_provider_metric_protocol.php`: **15 assertions PASS**.
- `php tests/Standalone/provider_performance_signals.php`: **12 assertions PASS**.
- `node --test tests/Frontend/provider-metric-sync-architecture.test.mjs`: **4/4 PASS**.
- `node --test tests/Frontend/*.test.mjs`: **154/157 PASS**. Three pre-existing startup/onboarding/membership tests could not resolve installed `@babel/parser` because `node_modules` is not provided. All other source tests passed.
- **NOT EXECUTED:** full Laravel PHPUnit suite, actual MySQL 8 migrations/rollback, real Kinship membership or worker concurrency, provider OAuth/HTTP integration, Vite build, browser E2E. Those require the user's local configured environment.

## Local integration checklist (disposable environment only)

1. Run `composer install --no-interaction` and `npm ci` on the correct supported PHP/Node environment. Supply required extensions including `pdo_mysql`, `mbstring`, `dom`, `xmlwriter`, `intl` and a **disposable** MySQL 8 database. Do not use `migrate:fresh` on any existing database.
2. `php artisan migrate --env=testing`, `php artisan route:list`, `php artisan test --testsuite=Unit`, `php artisan test --testsuite=Feature`, `php artisan test --testsuite=Integration`; verify up/down on a throwaway DB. Run `tests/Integration/Analytics/ProviderMetricSyncDurabilityTest.php` and `ProviderMetricSyncScheduleTest.php` specifically.
3. Use `Http::fake` to prove fixed Google request origin/method, no bearer tokens in observations, failed 401, 403, 404, 429, 5xx, network timeouts, malformed counts, wrong channel ID, data truncation and stable idempotency. Run concurrent workers against InnoDB: only one lease/observation may finish per post/day.
4. Validate that revoking organisation membership/brand role, expiring the OAuth grant, removing a linked channel or changing account status prevents further API reads.
5. Test the UI manually across light/dark themes, manager/viewer roles, daily policy concurrent updates, no published YouTube posts, missing stats and post snapshots spanning 14 days. Confirm scheduled reports do not combine manual impressions with YouTube views.
6. After provider app review, use test YouTube accounts and explicitly enable the **read-only** analytics flag. Do not enable the unrelated publishing or Storage delivery flags on this evidence alone.

References: https://developers.google.com/youtube/data/reference/rest/v3/videos/list and https://developers.google.com/youtube/data/guides/using_resumable_upload_protocol . YouTube video upload is an independent, still-disabled workstream.

## Five-batch SOURCE estimates (subjective, not test pass rates)

| Batch | Increment 56 | Increment 57 | Development status |
|---|---:|---:|---|
| 1. Publishing Engine | 90% | **90%** | IN PROGRESS — YouTube resumable Storage, provider approvals and verification remain |
| 2. Content Operations | 87% | **88%** | IN PROGRESS — complete media picker/browser scheduling integration remain |
| 3. AI Marketing Intelligence | 60% | **62%** | IN PROGRESS — provenance-aware deterministic performance signals added; AI evaluation still incomplete |
| 4. Analytics & Automation | 45% | **60%** | IN PROGRESS — opt-in real Google API read, audit-backed scheduling, reports and trend; five other providers remain |
| 5. Integrations & Production | 43% | **50%** | IN PROGRESS — guarded read-only provider client, API, leases, scope checks, tests; UTCP/MCP/deployment remain |
| **Unweighted overall** | **65%** | **70%** | **0/5 batches development-complete; runtime verification pending** |

These figures express estimated breadth of source functionality against the roadmap, not independently measured feature completion or production confidence. Do not increase them merely to meet a target.
