# Increment 49 — Opt-in daily UTC report automation (Batch 4)

## Status

**Source implemented; Laravel/MySQL, browser and production scheduler verification NOT performed.** The overall five-batch delivery remains unfinished. This feature adds repeatable, offline, metrics-evidence reporting; it is not social publishing and does not verify external metrics.

## Scope and safeguards

- Reuses the existing `automation_rules`, `automation_rule_versions`, `automation_runs` tables; no schema migration needed. Keeps prior manual rules and endpoints backward compatible.
- Managers/owners with Kinship `approval_request.update` can create a `DAILY_UTC` report-only rule, specifying UTC hour (0–23), rolling completed-day lookback (1–30), and daily cap (1–20). Creation starts in `DRAFT`; explicit activation via the existing authorized rule-status endpoint is required.
- Active rules appear in the existing React report panel with the schedule. Manual rules still use an explicit, idempotent run action; daily rules run only via the application scheduler.
- The job command `planner:process-scheduled-reports` is disabled by default. `--dry-run` may inspect due rules while disabled. Execution requires `PLANNER_REPORT_SCHEDULER_ENABLED=true` plus Laravel schedule:run or schedule:work. This opt-in does not affect publishing flags.
- The hourly Laravel scheduler uses `withoutOverlapping()->onOneServer()`. The service additionally locks the brand row and rule, recomputes the eligibility and rolling UTC dates within the transaction, deduplicates using SHA256 of date+immutable version ID, enforces the daily cap, and writes one `COMPLETED` run with snapshot provenance.
- Runs use ONLY `SocialMetricRepositoryContract::report()`, including its sample completeness and `USER_REPORTED_UNVERIFIED` provenance. The scheduler creates no social posts, HTTP requests, publishing jobs, content drafts, or external notifications.
- Runs are **not** automatically retried if metrics computation fails; the failure is reported to the operator logger and the command returns a failure code. Daily reports cannot execute via the manual endpoint.
- The scheduler is application-owned: it does not impersonate the rule's owner as a current human actor, and does not bypass HTTP authorization for creating, activating or retiring rules.

## Validation

- `node --test tests/Frontend/report-automation-architecture.test.mjs tests/Frontend/scheduled-report-automation-architecture.test.mjs` **7/7 PASS**.
- `node --test tests/Frontend/*.test.mjs` **117/120 PASS**; three pre-existing missing `@babel/parser` dependency failures.
- `php scripts/check_repository_boundaries.php` PASS (37 Eloquent repositories).
- `php scripts/check_kinship_catalog.php` PASS.
- PHP syntax check of all `app`, `database`, `routes`, `tests` PHP files PASS.
- `tests/Integration/Analytics/ScheduledReportAutomationPersistenceTest.php` authored but **NOT EXECUTED**; real MySQL and PHP extensions are unavailable here.

## Local acceptance on disposable DB

1. Install PHP extensions and Composer dependencies and run additive migrations on disposable MySQL (never `migrate:fresh` on production data).
2. `php artisan test --testsuite=Integration --filter=ScheduledReportAutomationPersistenceTest` and existing report workflow tests; verify idempotency, tenant isolation, disabled/paused/retired rules, concurrency and scheduling behavior.
3. Confirm `php artisan planner:process-scheduled-reports --dry-run` works with flag OFF and execution fails closed without flag.
4. When ready for an isolated environment, set `PLANNER_REPORT_SCHEDULER_ENABLED=true`; run `php artisan schedule:run` hourly or a scheduler daemon; check only evidence-report runs are written, with no provider network calls.
5. Run `npm ci`, full frontend tests, production build and UI browser checks.

## Known limitations

- Candidate scanning is bounded to 100 enabled daily rules per execution (default 25); add a cursor/pagination mechanism before high-volume deployment to prevent starvation.
- No external provider ingestion, verified engagement, campaign ROI, external message delivery or AI-triggered marketing action is included.
- Batch 4 and other four batch acceptance statuses remain OPEN. Pensuh.com legal/privacy rollout remains NOT DEPLOYED.
