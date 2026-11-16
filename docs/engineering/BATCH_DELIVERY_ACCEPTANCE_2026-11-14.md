# Planner: five integrated batch delivery gates

Date: 2026-11-14. This replaces increment-by-increment scope reporting as the delivery acceptance framework. **An increment's implemented source does not mean a batch is complete.**

## Batch 1 — Publishing Engine — NOT ACCEPTED

| Requirement | Evidence in current source | Gate still open |
|---|---|---|
| Provider capability matrix | Six-network lookup and credential readiness diagnostics | Attested production permissions, current provider documentation and real provider tests |
| Publishing adapters | Mock/offline contracts, request fixture simulator | Actual provider-side upload/create/status adapters with contract tests |
| Scheduling engine | Held, UTC-normalized idempotent planning; atomic bulk held planning (this change) | Explicit activation, revalidation, dispatch-time authorization and durable scheduled state |
| Workers | Offline simulator and sandbox ledger with leases | Real publish dispatch worker and transactional outbox |
| Retry and idempotency | Classified mock outcomes; occurrence key, sandbox deduplication | Provider-specific idempotency guarantees and proven retry boundaries |
| Failure recovery | Expired lease quarantine and two-person uncertain reconciliation | Live provider reconciliation and automated recovery of provably unsent requests |
| Publishing history/audit | Job timeline, attempt/event records and reconciliation | Verified remote post ID/status, provider evidence and retention policy |
| Operator UI | Held plans, bulk held plans, UTC calendar (this change), operations and reconciliation | Actual scheduling/publishing execution controls and full browser validation |

**Safety:** Live remote posting remains disabled and all held intents remain `AWAITING_USER` until a separately verified dispatch implementation is approved. The new calendar and bulk API are planning operations, not publishing permissions. An uncertain outcome is never replayed automatically.

## Batch 2 — Content Operations — NOT ACCEPTED

Existing drafts, immutable versions, media references, target selection, approval-to-held planning, bulk hold and UTC calendar UI are building blocks. Still required: full content calendar UX, validated provider variants, media rendition constraints, bulk publication workflow and provider-integrated scheduling.

## Batch 3 — AI Marketing Intelligence — NOT ACCEPTED

Implement grounded strategy retrieval and constrained generation, versioned prompt/model evidence, per-brand claims checks, proposed campaigns, cost/time budget limits, and explicit human approvals. Generated content must not bypass content versioning and review.

## Batch 4 — Analytics & Automation — NOT ACCEPTED

Implement provider reporting ingestion with provenance and rate limits; consistent metrics; campaign attribution and dashboards; safe automations with explicit approvals; idempotent event processing; monitoring and data retention.

## Batch 5 — Integrations & Production — NOT ACCEPTED

REST/UTCP/MCP endpoints must reuse existing business services and Kinship; deployment requires composer/npm installs, MySQL 8 migrations and rollback, PHPUnit, browser E2E, production secret/credential rotation, observability, disaster recovery, and provider/callback verification. Update pensuh.com privacy/legal/data deletion pages separately: not deployed.

## Mandatory release evidence

Do not mark a batch DONE without: (1) implemented backend & repository contracts; (2) migration and fixtures; (3) API, React and Kinship checks; (4) unit, feature, MySQL concurrency/integration and browser/provider contract tests actually run; (5) auditable evidence and deployment controls. Record failed and skipped tests exactly. Do not infer approvals from offline mocks.

## Current integrated change

- `POST /api/v1/organisations/{organisation}/brands/{brand}/content/publication-intents/bulk`: one transaction, 1–20 independently validated plans, no partial persistence, existing exact approval checks and audit behavior reused.
- `GET /api/v1/organisations/{organisation}/brands/{brand}/content/publication-calendar?from=YYYY-MM-DD&to=YYYY-MM-DD`: tenant-authorized read-only UTC date range, max 90 days, max 500 jobs, never a dispatch token.
- React content operations panel now has multi-target bulk held planning and UTC month calendar.
- No new migrations needed: existing job schema is reused. An additional MySQL integration test and frontend checks were authored; MySQL has not been executed in this environment.

### Immediate release blockers

1. Real Laravel/MySQL/Package Kinship/Storage runtime gates still outstanding; PHP extensions and configured disposable MySQL required.
2. Provider app review, per-account scopes and real platform publishing contracts are not verified.
3. Live dispatch disabled, provider evidence and safe retries not built.
4. OAuth/shared grant lifecycle and Pensuh.com data deletion/privacy obligations still require live verification.

## Continued source checkpoint — held-plan maintenance

Added locked/versioned reschedule, atomic bulk cancellation, calendar CSV export + filter, React operator actions and a new source integration test. These are **held plan management** features shared across Batch 1/2. Batch 1 and Batch 2 remain **NOT ACCEPTED** until their listed execution and validation gates are satisfied. Refer to `BATCH1_OPERATOR_MAINTENANCE_2026-11-14.md`.

## Internal operations analytics update (2026-11-14)

Source implemented: organisation+brand-scoped content/approval/plan/attempt summary and a read-only React dashboard. This is internal workflow analytics only. Provider metrics ingestion, attribution, reporting automation, and real platform engagement remain **NOT ACCEPTED** for Batch 4. See `43_INTERNAL_OPERATIONS_DASHBOARD_2026-11-14.md`.

## AI suggestions source continuation (2026-11-14)

Brand-scoped opt-in AI suggestion generation, recorded model/output hashes, review-only suggestion storage, editor promotion into normal draft/version flow, a React panel and test sources are added. This **does not complete Batch 3**, since campaign intelligence, comprehensive claim verification, usage budgets, and runtime tests remain open. See `44_BRAND_AWARE_AI_SUGGESTIONS_2026-11-14.md`.

## AI campaign proposal source update — Increment 45 (2026-11-14)

Added an AI-assisted campaign editorial proposal and independent-review desk (Laravel API, scoped Eloquent repository/factory/migration, React panel, audit records and test source). **Batch 3 remains NOT ACCEPTED** pending model usage budgets, more complete intelligence and runtime validation. All other batches remain open. See `45_AI_CAMPAIGN_PROPOSALS_INDEPENDENT_REVIEW_2026-11-14.md`.

## Metric evidence continuation — Increment 47

Operator-reported six-platform metric snapshots, source-provenance labels, deduplication, scoped reporting and React evidence desk added. **Batch 4 NOT ACCEPTED**: provider API ingestion and automation still outstanding. See `47_SOCIAL_METRIC_EVIDENCE_2026-11-14.md`.

## Increment 48 — Manual metrics report automations

Added tenant-safe manual report rule setup, activation/pause, idempotent run records, daily caps, audit and React desk using existing automation schema. The output is explicitly operator-reported, unverified evidence. It does **not** enable cron triggers, automatic actions, live provider ingestion or publishing. **Batch 4 remains OPEN**; other batches remain OPEN. See `48_MANUAL_REPORT_AUTOMATION_WORKFLOW_2026-11-14.md`.

## Increment 49 — Batch 4 progress (not accepted)

Daily UTC reports now have an explicit disabled-by-default scheduler, per-brand locked and once-per-date deduped execution, a rolling completed-day lookback, and operator UI. This does not complete Batch 4: verified provider ingestion, broader automation triggers, attribution and end-to-end testing remain open. See `49_SCHEDULED_REPORT_AUTOMATION_2026-11-14.md`.

## Increment 50 — Held plan preflight (2026-11-14)

Added bounded, read-only 1–50-plan preflight with approval/media policy revalidation, tenant-safe evidence lookup and React blockers panel. No posting is enabled. Current **estimated source coverage**: Batch 1 40%, Batch 2 65%, Batch 3 50%, Batch 4 45%, Batch 5 20%; all remain NOT ACCEPTED. See `50_HELD_PUBLICATION_PREFLIGHT_AND_BATCH_PROGRESS_2026-11-14.md`. Percentages are subjective implementation estimates, not test pass percentages.

## Increment 52 — Guarded text network transport (NOT ACCEPTED)

Added typed HTTP transport for Facebook/LinkedIn/X with a pinned egress policy, independent proof-of-authorization checks, strict credential/response isolation, and mockable HTTP tests. **No worker integration or enabled publishing**. See `52_GUARDED_PROVIDER_TEXT_HTTP_TRANSPORT_2026-11-14.md`. Current source coverage estimates: B1 46%, B2 65%, B3 50%, B4 45%, B5 20%; no accepted batches.

## Increment 54 — current publishing source status (supersedes outdated earlier snapshot)

The previous table described the pre-Increment 53 baseline. A lease-fenced **operator-armed text worker** now exists for Facebook Pages, LinkedIn members and X creators, protected by production kill switches. Increment 54 adds per-provider scoped, short-lived entitlement verification, Facebook Page-token exchange, receipt-ledger persistence, media preflight and operator UI. **Provider/media integrations are still not fully implemented or runtime-tested.** All batch acceptance gates remain OPEN. Source estimates B1 72%, B2 77%, B3 50%, B4 45%, B5 31%; mean ~55%; no development-complete batches. See `54_VERIFIED_PROVIDER_ENTITLEMENTS_AND_POST_LEDGER_2026-11-14.md`.


## Increment 57 — source analytics integration checkpoint (2026-11-15)

Implemented YouTube `videos.list` read-only analytics with constrained channel match, per-brand/day durable queued probes, opt-in schedule, independent immutable observations, provenance-separated dashboard and daily report automation, and same-video performance signals. See `57_YOUTUBE_PROVIDER_METRICS_AUTOMATION_2026-11-15.md`. **Source estimate ~70%, all five batches still OPEN; real MySQL/Kinship/Google/provider/CI testing deferred. No publishing release toggled.**

## Increment 58 — YouTube resumable, Storage picker and editorial risk checks (2026-11-15)

YouTube upload INIT/probe/chunk/verification now has a source-level durable ledger, encrypted Google session, transaction-fenced attempts, independent provider receipt verification, explicit release gates and a default-denying Storage byte-reader. The media library now exposes a scoped canonical Storage picker, and AI suggestions have a read-only editorial claim-risk check. **No live provider transfer or publication is enabled or verified.** Acceptance estimates B1 95%, B2 94%, B3 67%, B4 60%, B5 59%, mean ~75% source coverage; **0/5 accepted batches**. See `58_YOUTUBE_RESUMABLE_STORAGE_BROWSER_AI_EDITORIAL_REVIEW_2026-11-15.md` and local gates. This supersedes statements in earlier notes that a YouTube stage runner does not exist; the existing generic media worker intentionally still skips YouTube.

## Increment 59 — read-only X analytics / evidence-backed AI campaign source (2026-11-15)

Extended existing provider-sync pipeline to read confirmed X posts (separately gated OFF by default), normalized provider metrics, exposed unit-separated comparisons, and persisted optional immutable API-observed campaign evidence in AI proposals. Laravel/MySQL and X billing/permission validation are outstanding. Latest estimated source coverage B1 95%, B2 95%, B3 76%, B4 75%, B5 59%, overall 80%. **All 5 batches remain NOT ACCEPTED.** See `59_X_PROVIDER_ANALYTICS_AND_AI_EVIDENCE_2026-11-15.md`.


## Increment 61 — Batch acceptance checkpoint (2026-11-15)

New source: `docs/engineering/61_MCP_AI_USAGE_OPERATIONAL_ALERTS_2026-11-15.md`. Added five read-only Laravel MCP tools using scoped Sanctum/Kinship; provider-observed nullable AI token evidence; opt-in tenant-scoped durable operational alerts. **Package `laravel/mcp ^1.2` declared, NOT vendor-installed, composer.lock deliberately preserved**. Overall estimated source scope **90%** (B1 95, B2 95, B3 82, B4 88, B5 90), **zero accepted batches**, all MySQL/Composer/MCP/browser/provider/CI runtime gates pending.

## Increment 62 — source coverage ~95%, no accepted batch

A reviewed Tetranyble Storage v3 stream and short-lived signed origin adapter, independently gated operator integrity verification, emergency publish halt, priced-but-not-invoiced AI token evidence, durable opt-in alert email with uncertain-send quarantine, and sanitized production gate exist in source. **All five batches remain NOT ACCEPTED** pending installation, real MySQL/Storage/OAuth/media/provider/browser, recovery and deployment tests. Details: `62_STORAGE_V3_COST_ALERTS_RELEASE_SAFETY_2026-11-15.md`.

## Increment 63 — Final source candidate and validation gate (2026-11-15)

New separately gated Facebook Page, TikTok and Instagram read-only provider analytics extend the existing fenced/brand-scoped metric collection queue to five sources. Per-provider data remains unit-separated and no release switch is enabled. Added MySQL dump/checksum/scratch-only restore rehearsal, safe missing environment templates, PHP unit/integration test source and CI/structural migration guard improvements. **All batches remain OPEN pending actual Composer/Storage/MySQL/provider/MCP/browser and legal deployment verification.** Source estimates B1 98%, B2 98%, B3 90%, B4 99%, B5 98%, overall **~97%**, 0/5 accepted. See `63_FINAL_SOURCE_CANDIDATE_AND_RUNTIME_GATES_2026-11-15.md` for executed tests, remaining gaps and exact local acceptance sequence. This is not a claim of 100% development.
