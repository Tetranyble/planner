# Increment 59 — X Read-only Analytics + Evidence-bound AI Campaigns

Date: 2026-11-15. Full-source baseline: Increment 58. This is **SOURCE IMPLEMENTED / RUNTIME UNVERIFIED**, not a release. Source estimate ~80% (B1 95%, B2 95%, B3 76%, B4 75%, B5 59%); **0/5 batches accepted**. The source percentages are engineering judgments, NOT coverage measurements or evidence of production readiness.

## Workstream A: extend the same durable analytics pipeline to X

- `XPostStatistics`: reject invalid X numeric IDs, author mismatches, malformed, negative, inflated, and absent counters. Preserve null vs actual zero. `public_metrics.impression_count` is represented in the existing `views` observation field **only for X**, and is labeled *X public post impressions* in the UI. No addition across YouTube views and X public impressions.
- `LaravelXPostStatsClient` / `XPostStatsClientContract`: read-only `GET https://api.x.com/2/tweets/{id}?tweet.fields=author_id,public_metrics`; host/path pinned, no redirects, no HTTP retries, no token or raw error persistence. Real X API access and plan-dependent costs have NOT been verified. X endpoint/field expectations are based on public X API v2 material available during authoring, and must be verified with the developer project before enablement.
- Reuse existing `provider_metric_sync_runs`, `provider_metric_observations`, and per-brand daily policy, including uniqueness, locks, recovery, 3-attempt limit, exact post/account identity, tenant constraints and immutability. **No second queue or media copies**.
- `PLANNER_PROVIDER_METRIC_SYNC_ENABLED=false` is the global read-only gate; **independent** `PLANNER_X_METRIC_SYNC_ENABLED=false` prevents paid X reads and X autoqueue until deliberately enabled. Existing YouTube remains opt-in; all publishing kill switches remain OFF.
- Analytics response now has **provider_breakdown** with separately labeled totals, null on unknown metrics and no partial totals when capped. Legacy single-provider totals are still returned only if one provider contributed; multi-provider totals intentionally null. 14-day matched-post comparison evaluates separately for YouTube and X and requires at least 3 complete pairs.
- React sync panel and evidence dashboard updated for the two platforms. X read remains constrained to locally confirmed posts and the account that posted them.

## Workstream B: opt-in evidence-backed campaign planning

- `ProviderEvidenceContext`: deterministic, source-specific evidence builder using only **PROVIDER_API_OBSERVED** matched-post trends, never manually entered social metrics. Requires at least three same-post pairs, excludes truncated, zero-baseline and mismatched-platform samples. Evidence snapshots describe observed counts, not campaign lift, best posting times, conversion or ROI.
- The AI Campaign Proposal API accepts optional `use_provider_evidence`; default false. When true and no eligible observations exist, it returns validation feedback rather than inventing performance data. The existing AI usage budget and independent campaign reviewer remain mandatory.
- Additive migration `2026_11_15_120500_add_ai_campaign_provider_evidence.php`: nullable canonical evidence JSON and SHA-256 hash on `ai_campaign_proposals` (no existing migration edited). Repository persists both with the AI prompt/output hashes. Independent review and proposal-to-draft promotion revalidate the canonical digest; JSON key reordering by MySQL does not invalidate the snapshot.
- React campaign UI offers explicit opt-in and shows the evidence digest and number of matched provider signals. Campaign approval never authorizes posting or bypasses per-content approval.

## Tests and honest limitations

Source-level tests: `php tests/Standalone/x_provider_metric_protocol.php` (16 checks), `php tests/Standalone/campaign_provider_evidence.php` (14 checks), and existing `php tests/Standalone/provider_performance_signals.php` (12 checks). Frontend architecture tests in `tests/Frontend/x-provider-metrics-architecture.test.mjs` (3) and `campaign-provider-evidence-architecture.test.mjs` (2). PHPUnit unit and mocked HTTP transport tests authored; two additional factory-backed MySQL integration tests authored. Full frontend regression and PHP lint results are recorded in the handoff after running.

**NOT executed here:** Composer PHPUnit, disposable MySQL 8 migrations/rollback, Kinship session resolution and permission cache, actual X developer subscription/author permissions, actual YouTube/X provider API, concurrent worker leases, Vite production build and browser E2E. Three existing frontend tests cannot load `@babel/parser` in this environment. No social posting was enabled or tested.

## Local verification for user (use disposable MySQL first)

1. `composer install`, `npm ci`, configure `.env.testing` with proper MySQL 8 and Kinship/Storage; ensure PHP extensions `pdo_mysql`, `mbstring`, `dom`, `xmlwriter`, `intl` installed. Do not edit vendor code.
2. Run `php artisan migrate` on a disposable database; check `ai_campaign_proposals.provider_evidence_snapshot` persistence and rollback. Do not run `migrate:fresh` on existing user data.
3. Run `php artisan test --testsuite=Unit`, `--testsuite=Feature`, `--testsuite=Integration`; then `node --test tests/Frontend/*.test.mjs` and `npm run build`.
4. Test X post belonging to connected account, mismatched author, scope downgrade, revoked access, 429/503/timeout, expired leases, concurrent daily queue, wrong tenant, missing impression_count, null vs zero, and mixed-platform snapshots. Confirm no accidental HTTP POST, no secrets in API payloads, no provider writes and no false ROI/combined totals.
5. Test `use_provider_evidence` true with insufficient/truncated samples (422), valid samples, review by other user, MySQL JSON reorder digest, draft creation and normal content approval. No automatic publication.
6. Only after runtime evidence and verified API plan/permissions, consider X read flag in a controlled test brand. All social publishing release flags remain disabled. Pensuh.com privacy/legal/cookie/data-deletion public page workstream remains tracked, **NOT DEPLOYED**.

## Remaining five-batch blockers

B1: verified Tetranyble Storage byte-source and distribution, real multi-provider permission/transport tests, MySQL concurrency/confirmed delivery. B2: media/browser full runtime and provider-specific QA. B3: stronger evidence verification, campaign planning E2E and model usage reconciliation. B4: Facebook/Instagram/LinkedIn/TikTok analytics where permitted, attribution limitations and broader automations. B5: UTCP/MCP, complete production CI/security/monitoring, published Pensuh legal pages, provider app approvals. **0/5 accepted.**
