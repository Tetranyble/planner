# Increment 69 — Recurring campaign templates and human-held occurrences

**Base:** Increment 68, 2026-11-15. **Status:** SOURCE IMPLEMENTED; NOT live-accepted.

## What changed

A previously passive `recurring_schedules` table now has a first-party operation path. Authorized operators may create versioned DAILY / WEEKLY campaign templates for an existing immutable `content_target`, preview up to 90 days, pause/resume/irreversibly retire templates, and explicitly hold individual approved occurrences. This is **not** an automatic campaign publisher or an autonomous posting loop.

* Server-side RRULE parser accepts **only** `FREQ=DAILY|WEEKLY`, `INTERVAL=1..30`, and optionally `BYDAY=MO,TU,...` for weekly rules. Rejects unknown fields, duplicates, bad weekdays and minutely/hourly frequencies. `max_occurrences` is explicitly capped at 180 at input; service/domain hard limit 366. Recurrence window is bounded to 366 calendar days from the template's start; previews are limited to 90 days and must have explicit time-zone offsets.
* Timezone inputs use IANA IDs. Wall-clock recurrences are converted to UTC without silently changing user intent: nonexistent spring-forward and ambiguous fall-back times are **skipped** and counted. No guessed choice of which 01:30 to use during DST fall-back. UTC instants and stable UUIDv5 occurrence keys derive from schedule id + exact wall time + UTC instant; restart/retry does not duplicate the occurrence. Global occurrence caps count previously elapsed slots, not just those visible in a later preview.
* A schedule stores an existing, tenant-scoped content target and its author. It does **not** store permission to publish, a reusable approval assertion, or a media-upload bypass. Every `hold` requires an explicit first-party request, active schedule, expected optimistic version, exact re-derived UTC occurrence, a supplied existing independent approval and an existing current content version. It delegates to `ManagePublicationIntents::create()` to re-evaluate brand, approval, current hash, risk and Storage safety, creating **AWAITING_USER** only; `max_attempts=0`, no arming or dispatch. Previously cancelled/advanced occurrences cannot be reopened with the same key. A repeated identical hold remains idempotent without a duplicate occurrence audit.
* Brand and organisation row locks, first-party auth/verified session, Kinship route+service permissions, hard-scoped repository queries, version CAS and audit events protect creation, state transition and hold. Retired schedules cannot reactivate. Pausing/retiring does **not** silently cancel previously confirmed held jobs; these remain under their existing independent approval and cancellation controls.
* Added operator interface in Publication Planning Desk and additive migration for schedule `version`. Existing target rows remain immutable. Added controllers with six-action/invokable conventions, a contract-bound Eloquent repository, a standalone recurrence CLI test, PHPUnit Unit/Feature/Integration tests, and source architecture checks.

## New API routes

Relative to `/api/v1/organisations/{organisation:uuid}/brands/{brand:uuid}/content`:

| Method | Route | Semantics |
|---|---|---|
| GET | `/recurring-campaigns` | list up to 100 brand-scoped templates |
| POST | `/recurring-campaigns` | save new template (`target_uuid`, `rrule`, `timezone`, `start_local`, `max_occurrences`, optional `end_at`) |
| GET | `/recurring-campaigns/{scheduleId}/preview?from=...&to=...` | read-only, offset-aware recurrence preview |
| POST | `/recurring-campaigns/{scheduleId}/state` | optimistic `state` + `expected_version`; manual pause/resume/retire |
| POST | `/recurring-campaigns/{scheduleId}/hold` | `run_at` exact `Z` UTC preview, `approval_request_id`, `expected_version`; creates an **operator-held** publication plan only |

No route accepts a `can_dispatch`, remote provider receipt, arbitrary `occurrence_key`, client-provided approval status, or execution-enable flag.

## Boundaries, verification, limitations

- Runtime dependency installation, real MySQL schema application, Kinship auth in a deployed app, Tetranyble Storage and provider/OAuth sandbox calls were **not** tested here. New PHPUnit tests are authored but **not executed** without the project's Composer installation and database. `npm run build` and browser workflow are also not executed without package dependencies.
- Standalone deterministic recurrence checks cover Lagos UTC offset, weekly days, global cap, stable UUIDv5, DST gap/fold, and invalid RRULEs. Source architecture checks enforce lock/version/permission/readiness paths.
- `next_expand_at` deliberately remains `NULL` on new records: **there is no unattended expansion worker**. Do not describe this as autonomous recurring publishing. A future worker would require separate consent and independent approval per occurrence, and should never auto-arm a provider post.
- This does not complete the earlier outstanding **provider→Tetranyble Storage autonomous binary ingestion**: the verified package-owned upload API, virus scan, ACL and tenant trust contract still require local runtime validation. Never implement a second guessed uploader to claim completion.
- Increment 68's private-image review/handoff, AI provenance and content declarations remain intact. Pensuh.com privacy, data-deletion and retention publication remains an external deployment step.
- All production/social/AI release flags remain off by default. `composer.lock` and `package-lock.json` are unchanged.

## Static verification performed in this environment

| Check | Actual result |
|---|---|
| PHP syntax lint across all project PHP files | **1,450 / 1,450 pass** |
| Node source/frontend checks | **229 pass / 232 total**; three known failures import missing `@babel/parser` (`brand-membership-pages`, `membership-pages`, `onboarding-pages`) |
| Recurrence portable assertions | **16 / 16 pass** (`php scripts/check_recurring_slots.php`) |
| Recurrence source architecture tests | **4 / 4 pass** |
| Repository guard | **PASS** (57 Eloquent repositories) |
| Kinship catalogue guard | **PASS** (138 models, 998 discovered + special permissions, 4 roles) |
| Static migration structure | **PASS** (145 application-owned tables); no executed DB migration |
| `composer.lock` and `package-lock.json` | **SHA-256 unchanged versus Increment 68** |
| PHPUnit, MySQL, real browser/build/provider/Storage/OAuth | **NOT EXECUTED** |

## Local acceptance sequence

```bash
composer validate --no-check-publish
composer install  # only after reconciling the existing laravel/mcp package lock discrepancy
npm ci
php artisan migrate --env=testing --force  # disposable MySQL 8 database ONLY
php artisan test --filter=RecurringSlotCalculatorTest
php artisan test --filter=RecurringCampaignRepositoryTest
php artisan test --filter=RecurringCampaignEndpointsTest
node --test tests/Frontend/*.test.mjs
npm run build
```

In an actual browser, create two tenants/brands, two independent approvers and a verified Storage image target. Create a recurrence in Africa/Lagos, preview and hold one approved occurrence, retry the same request, pause and attempt another hold, retire and test reactivation, and try a cross-brand target. Check a mismatched `run_at`, an expired/superseded approval, a quarantined media asset, untrusted timezone, DST ambiguous time, and concurrent stale `expected_version` updates. Assert no provider HTTP call occurred and the held job remains `AWAITING_USER`.

## Source completion

**Provisional original-five-batch aggregate remains approximately 98%**, consistent with Increment 68's 97.8% estimate; source progress is not test acceptance. Batch statuses remain **IN PROGRESS / NOT ACCEPTED** (0/5 accepted). This increment closes a meaningful missing recurrence source workflow but does not justify declaring the full expanded autonomous marketing platform complete or declaring production ready.
