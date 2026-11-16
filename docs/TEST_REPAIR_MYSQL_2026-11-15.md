# Planner — MySQL test stability and regression repair (November 15, 2026)

Source: the user-supplied `Planner(2).zip` and its PHPUnit failure log. This handoff deliberately uses MySQL; it does not change the application to SQLite. **Testing must NEVER point at the live MySQL database.** `RefreshDatabase` drops and recreates test tables, and the CI workflow explicitly uses `migrate:fresh`.

## Changes

- `.env.testing` in the supplied archive declared `APP_ENV=local`; the working copy has been corrected to `testing`. The outgoing archive excludes this private file. Use `.env.testing.example` instead and fill in disposable database credentials. The PHPUnit configuration forces `APP_ENV=testing` and the `mysql` connection.
- `Tests\TestCase` now fails closed **before** Laravel's `RefreshDatabase` hook if the resolved database is not a localhost MySQL schema with a name ending `_test` or `_testing`, or if `DB_URL` overrides it. It also skips Laravel's `PreventRequestForgery` middleware *only for HTTP feature tests*, where there is no browser CSRF session. The production middleware remains intact.
- A matching `scripts/assert_safe_test_database.php` boots Laravel and checks the **resolved** configuration rather than relying on shell variables. The CI job now has the previously missing `.env.testing.example`.
- `YouTubeResumableTransfer`, `YouTubeResumableAttempt`, and `YouTubeResumableEvent` explicitly map to the migration's `youtube_resumable_*` table names, not Eloquent's inferred `you_tube_*` names.
- An agent-isolation regression test now uses the existing `/api/v1/organisations/{organisation}/brands/{brand}/content` route, not the nonexistent `/content/items` route. The important expectation (403 for a scoped agent token on normal app mutations) remains.
- The TikTok provider-metric regression now correctly expects `youtube.totals === null` when there are no observations; it no longer dereferences `youtube.totals.views`.
- The Kinship host-config test now agrees with the enabled discovery setting and the other Kinship catalog tests (`true`).
- `ContentVersion` now rejects Eloquent updates at `performUpdate`, even when event listeners are suppressed; its existing update/delete event guards remain. A unit regression exercises the event-faked update path.
- A direct PHPUnit runner uses separate PHP processes for Unit, Feature, and Integration suites, avoiding `artisan test`/Collision output highlighting and freeing memory between suites. Memory is capped per process (`512M` default), and optional per-directory isolation further reduces memory peaks. CI and `composer test` invoke this runner.

## Local setup — MySQL 8, separate database

1. Create a **disposable database** called `planner_test` and a test-only MySQL user granted privileges *only* on `planner_test.*`.
2. Configure `.env.testing` with `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_DATABASE=planner_test`, test-only username/password, and `DB_URL=` empty. If starting fresh, copy `.env.testing.example` without overwriting any file containing your real test credentials.
3. Install dependencies and generate a separate testing key (not your production key):

   ```bash
   composer install
   cp -n .env.testing.example .env.testing
   php artisan key:generate --env=testing --force
   ```

4. If running from a local development checkout (not a live deployment), remove any stale *local* cached config: `php artisan config:clear`. Do not run this against a production deployment.
5. Confirm the preflight and run a focused test before the full suites:

   ```bash
   php scripts/assert_safe_test_database.php
   bash scripts/run_mysql_tests.sh Feature --filter=AuthenticationTest --stop-on-failure
   bash scripts/run_mysql_tests.sh Integration --filter=YouTubeResumableDurabilityTest --stop-on-failure
   bash scripts/run_mysql_tests.sh all
   ```

6. If peak memory is still high, run smaller groups in separate PHP processes:

   ```bash
   PLANNER_TEST_SPLIT_BY_DIRECTORY=1 PHP_TEST_MEMORY_LIMIT=384M bash scripts/run_mysql_tests.sh Feature
   PLANNER_TEST_SPLIT_BY_DIRECTORY=1 PHP_TEST_MEMORY_LIMIT=384M bash scripts/run_mysql_tests.sh Integration
   ```

**Do not use production database credentials for any of these commands.** The programmatic guard is another line of defence, not a substitute for choosing the correct database and account.

## Verification / outstanding issues

- Passed here: PHP syntax lint of all 1,526 files; repository architecture; fixture and Kinship contract checks; migration structure; six recovery checks; 9 creative preview and 13 image fingerprint standalone checks; shell syntax of the new runner.
- Unable to run here: Laravel/PHPUnit integration with MySQL, because Composer `vendor/`, the MySQL PHP extension, and a dedicated MySQL test instance are unavailable. The corrections are source-level fixes pending a rerun on the owner's local machine.
- The frontend source-contract suite also showed failures in this clean, dependency-free environment (196 of 235 assertions passed, including three test files blocked by uninstalled `@babel/parser`). Many additional failures check exact PHP source strings and may be stale against refactored code. These require individual behavior-oriented review and must not be counted as passing. Run `npm ci` then `node --test tests/Frontend/*.test.mjs` to reproduce after installing dependencies.
- Any newly revealed MySQL exceptions after the 419 failures are resolved should be reported with their first stack trace, migration name, and database schema state. Do not treat an incomplete test run as a clean acceptance gate.
