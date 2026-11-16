# Increment 43 — Internal content-operations dashboard (source implemented)

## Scope delivered

- Added `BrandOperationsMetricsRepositoryContract` and its strictly organisation/brand-scoped Eloquent implementation. Counts content items, platform variants, approval requests by status, publication plans by state, overdue held plans, and publication attempts. Aggregates in SQL; no cross-tenant row fetches and no provider data.
- Added `BrandOperationsDashboard` service with a second, explicit `BrandPermissionGuard` check plus route-edge Kinship `approval_request.index` authorization. The service enforces an inclusive UTC range of at most 90 days and returns an exclusive SQL upper bound.
- Added read-only `GET /api/v1/organisations/{organisation:uuid}/brands/{brand:uuid}/content/operations-dashboard?from=YYYY-MM-DD&to=YYYY-MM-DD`, a controller following the `__invoke` convention, and a content-studio React panel with date filters, 6 KPI tiles and state summaries.
- Added source MySQL integration coverage for tenant isolation and exclusive upper time bound; added frontend architecture assertions.

## Explicit limitations

- Internal operational counters are **not** social media impressions, engagement, reach, revenue, conversion, or ROI. No provider metrics have been ingested.
- `overdue_held` intentionally counts all overdue held plans as of the request time, rather than only plans created inside the dashboard period. This is labeled in the UI.
- No schema changes or production migrations are needed for this slice.
- No live publishing, automatic campaign execution, or AI model calls were introduced.

## Local runtime verification still required

1. Run `composer install`, ensure PHP extensions and MySQL are configured, and run existing additive migrations on a disposable MySQL 8 database.
2. Execute `php artisan test --testsuite=Integration --filter=BrandOperationsMetricsTest`.
3. Run `npm ci && npm run build` and browser tests for the content studio on a user with `approval_request.index` role.
4. Validate tenant 403 behavior, analytics correctness under non-UTC server settings, and the 90-day upper bound.
5. Test with sufficiently large organisations for index/query plan analysis.

## Acceptance scope status

Batch 1: IN PROGRESS — live publishing and provider dispatch are missing.
Batch 2: IN PROGRESS — full content operations and provider media preflight are missing.
Batch 3: NOT COMPLETE — AI generation/campaign planning providers not implemented.
Batch 4: IN PROGRESS — internal dashboard added, but provider ingestion and safe automation absent.
Batch 5: NOT COMPLETE — live E2E, deployment, UTCP/MCP and compliance remain.

This is not a five-batch-complete release. Do not advertise source-only changes as runtime verified.
