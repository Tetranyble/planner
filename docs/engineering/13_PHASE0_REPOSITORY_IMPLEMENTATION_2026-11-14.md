# Phase 0 repository implementation and CI handoff — 2026-11-14

## Baseline and source authority

Based on user-uploaded `Planner(1).zip` (Laravel 13; React/Inertia 19; Tailwind 4; shadcn). User reports having locally validated the submitted source. No existing frontend files, migrations, Kinship or Tetranyble Storage configuration were overwritten during this work.

## Implemented code (P00-05, still requires full Laravel test execution)

1. `UserRepository::save(array $attributes)` was removed: Eloquent `Model::save(array $options)` expects **options**, not model attributes. Use inherited `create($attributes)` or `update($model, $attributes)`. Introduced `UserRepositoryContract` and `findByEmail`.
2. `BrandScope` enforces positive organisation and brand IDs and rejects identity fields in write payloads.
3. `BrandScopedPersistence` and `BrandScopedRepository` are a **separate** repository hierarchy that intentionally does not extend unrestricted `BaseRepository`. All reads, writes, pagination and deletes require `organisation_id` AND `brand_id`.
4. `CampaignRepositoryContract` and `CampaignRepository` provide the first concrete brand-scoped persistence implementation. The Campaign model explicitly allows permitted attributes while guarding `organisation_id`, `brand_id`, primary key and UUID. The scoped base rejects unknown mass assignment keys, even when Eloquent is configured to silently discard attributes.
5. Bind repository interfaces to Eloquent implementations in `AppServiceProvider`. No controllers or services call new unscoped query methods.

**Security boundary:** Repository scope is **not permission checking**. A service must first validate the authenticated user, selected organisation, Kinship capability and active brand membership before calling a brand repository. Do not expose these repository contracts directly as unauthenticated UTCP/MCP tools. Business services must own transactions, lifecycle checks and outbox writes.

**Concurrency limitation:** Simple update methods load and then save a scoped model; these are appropriate only for non-concurrent mutable records. Approval, scheduling, ledger-like or optimistic-locking operations require atomic conditional SQL and transaction-level concurrency checks in dedicated repositories.

## Tests authored

- `tests/Unit/Repository/BrandScopeTest.php`: invalid identifiers and protected fields.
- `tests/Feature/Platform/RepositoryContractBindingsTest.php`: Laravel bindings and absence of unrestricted accessor inheritance.
- `tests/Integration/Repository/CampaignRepositoryTest.php`: multi-organisation and multi-brand isolation for read, create, update, delete and pagination; rejects spoofed/unknown write fields.
- `tests/Integration/Repository/UserRepositoryTest.php`: global user identity creation and retrieval.
- `tests/Integration` added to `phpunit.xml`.
- `scripts/verify_brand_scope.php` is a dependency-free guard smoke check; **not a replacement for PHPUnit**.
- `scripts/check_repository_boundaries.php` rejects new Eloquent brand repositories that extend the global base.
- `scripts/assert_safe_test_database.php` refuses destructive migration commands when the env is not explicitly a MySQL *test* database.

## CI and local validation procedure (P00-06)

Workflow: `.github/workflows/test.yml` starts a disposable MySQL 8 service, installs locked Composer dependencies on PHP 8.4 and Node 22, runs migrations/up-down-up, repository guards, unit suite, feature suite, real-MySQL integration suite, and a Vite production build. The workflow has **not run yet** in this workspace.

Run locally with PHP 8.4 and required extensions installed (`intl`, `mbstring`, `dom`, `xmlwriter`, `pdo_mysql`, plus Composer requirements), a disposable MySQL 8 DB, Composer and Node 22:

```bash
cp .env.testing.example .env.testing
# Replace DB_PASSWORD with a dedicated TEST database user password.
composer install --no-interaction --prefer-dist
npm ci --ignore-scripts
php artisan key:generate --env=testing --force
# Set DB_CONNECTION, DB_HOST and DB_DATABASE explicitly in the process env for the guard.
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=planner_testing php scripts/assert_safe_test_database.php
php artisan migrate:fresh --env=testing --force
php artisan migrate:rollback --env=testing --step=1 --force
php artisan migrate --env=testing --force
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
npm run build
```

Never run `migrate:fresh` on a persistent/prod database. Use dedicated disposable MySQL and existing migration history to plan live upgrades.

## Evidence collected in current execution environment

| Check | Result |
|---|---|
| PHP 8.4.24 CLI | Available |
| Vendor code in uploaded archive | Present, exact locked packages not reinstalled |
| PHP lint on new/modified repository, tests and scripts | PASS (no syntax errors) |
| `php scripts/verify_brand_scope.php` | PASS: 10 assertions |
| `php scripts/check_repository_boundaries.php` | PASS: 2 Eloquent repositories |
| Dedicated MySQL name guard with `planner_testing` | PASS |
| PHPUnit (`php vendor/phpunit/phpunit/phpunit ...`) | BLOCKED: missing `dom`, `mbstring`, `xmlwriter` PHP extensions |
| Real MySQL migration/integration | BLOCKED: no `pdo_mysql` PHP driver or MySQL service |
| npm production build with uploaded macOS node_modules | BLOCKED: `@rolldown/binding-linux-x64-gnu` missing (platform-specific native binary); run `npm ci` on Linux |
| GitHub Actions | Authored, not executed |

The user reports earlier local validation of the baseline. That does not constitute evidence that these new changes passed full PHPUnit/MySQL tests.

## Continuation — exact next actions

1. On a machine/CI with the required PHP extensions and MySQL, run the workflow and fix any migrations/package or factory failures. Record exact PHPUnit results and MySQL version.
2. Validate the Campaign repository creation and cross-tenant tests with real MySQL. Confirm `Campaign::Uuidable` and factories work in the installed version.
3. Verify Kinship/Storage runtime contracts and permission cache under active organisation changes; test shared organisations and media ownership with package APIs (P00-03/04).
4. Repair pre-existing skipped baseline tests and any failing tests from the newly enabled CI (P00-07).
5. Only after Phase 0 runtime gates pass, implement P01 authentication/organisation services via Contracts/Eloquent repositories, with unit/feature/MySQL integration tests, then React/Inertia/shadcn screens.

## Exclusions

No REST marketing controllers, functional campaign service, provider HTTP adapters, social publishing, UTCP tool writes or MCP write endpoints were added. No schema migrations changed. No claims of live deployment or completed package integration.
