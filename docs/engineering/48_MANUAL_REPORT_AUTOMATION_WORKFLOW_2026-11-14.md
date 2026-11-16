# Increment 48 — Brand-scoped report automation (source implementation)

## Delivered

Reuses existing `automation_rules`, `automation_rule_versions`, `automation_runs` tables. Adds typed `ReportAutomationRepositoryContract` / Eloquent implementation, `ManageReportAutomations` service, three API controllers (resource `index/store`, two invokable commands), scoped Kinship route guards, a React content-studio panel, Eloquent factories, a MySQL integration test source and dependency-free frontend safety tests.

The workflow: manager creates a **MANUAL** / **METRICS_REPORT_ONLY** draft with immutable version 1 and UTC range, enables or pauses it, triggers it with a supplied idempotency key, and views persisted report outcomes. It checks tenant and brand permission both at HTTP edge and service layer, brand-locks mutations, imposes 1–20 runs/day, and audits rule creation, status, and run completion in the same transaction. The report uses `SocialMetricRepository::report` exactly, preserves source labels `USER_REPORTED_UNVERIFIED`, and does not display incomplete sampled aggregates. No remote API calls, network side effects, scheduling, or social publication.

## Endpoints

Base: `/api/v1/organisations/{organisation:uuid}/brands/{brand:uuid}/content/report-automations`

- `GET /` — list up to 100 rules and 30 recent runs, permission `approval_request.index`
- `POST /` — create manual report rule, name/from/to/max_runs_per_day; manager or owner and `approval_request.update`
- `POST /{ruleId}/status` — `ENABLED`, `PAUSED`, `RETIRED`; retired cannot be reactivated
- `POST /{ruleId}/run` — idempotency key length 16–128, safe synchronous report aggregation and durable completion record

## Engineering boundaries

- Reports originate only from user-supplied, unverified metrics evidence; no engagement claims are provider-confirmed.
- Dates use completed UTC day windows, at most 90 days. The underlying metric repository withholds truncated aggregate totals.
- Existing schema is reused; no additive migration is required. Existing automation factory placeholders were filled.
- Rule version and trigger key are immutable at the application service boundary. Workers, cron, and outbound actions are not enabled.
- Rule display must not imply verified ROI/revenue or autonomous AI action.

## Verification evidence

- `node --test tests/Frontend/report-automation-architecture.test.mjs`: 4/4 PASS.
- `node --test tests/Frontend/*.test.mjs`: 114/117 PASS; three existing parser dependency failures.
- `php scripts/check_repository_boundaries.php`: PASS (37 Eloquent repositories).
- `php scripts/check_kinship_catalog.php`: PASS.
- PHP syntax checks under `app`, `database`, `tests`, `routes`: PASS (no error lines).
- `tests/Integration/Analytics/ReportAutomationPersistenceTest.php` authored, **not executed** against Laravel/MySQL.

## Outstanding production/acceptance gates

Run `composer install`, `npm ci`, migrations and rollback on disposable MySQL 8, Laravel PHPUnit, browser tests with Kinship roles, and verify metrics reporting transaction semantics. The broader Batch 4 requires verified provider ingestion, campaign attribution, scheduled and event-driven automation, dashboards and E2E. Live provider posting remains disabled. Also run all previously deferred phases and complete pensuh.com privacy/legal deployment review before release.
