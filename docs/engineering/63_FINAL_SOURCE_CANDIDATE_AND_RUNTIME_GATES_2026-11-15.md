# Increment 63 — Five-provider analytics, safe restore rehearsal and final local acceptance gate

Date: 2026-11-15. Baseline: full source from `Planner_Increment62_Storage_Production_Reliability_2026-11-15.zip`. This is the **complete updated source candidate** for the user's Laravel/MySQL testing. It is **NOT a production release**. Do not mark any batch accepted or enable an outbound release switch based on standalone checks.

## Scope implemented in this increment

### 1. Five separate provider analytics sources (Batch 4)

Extended the existing lease-fenced `ProviderMetricSyncRepository`, `ProcessProviderMetricSyncs` and `ManageProviderMetricSyncs` rather than adding a separate ingestion subsystem. New read-only adapters and explicit, default-OFF release gates:

| Network | Observation | Identity and entitlement preflight | Release environment variable |
|---|---|---|---|
| YouTube | Video views and engagement | Confirmed video and connected channel; read-only Google scopes | `PLANNER_PROVIDER_METRIC_SYNC_ENABLED` |
| X | Public impressions and public engagement | Confirmed X post; X author and `tweet.read` scope | `PLANNER_X_METRIC_SYNC_ENABLED` |
| Facebook Pages | `post_media_view` (not deprecated `post_impressions`) | Matching Page post, selected Page, `pages_read_engagement`, and `ANALYZE`/`MANAGE` task on a freshly resolved Page token | `PLANNER_FACEBOOK_METRIC_SYNC_ENABLED` |
| TikTok | Public video view/like/comment counts | Confirmed video, `video.list`, current TikTok actor and brand binding | `PLANNER_TIKTOK_METRIC_SYNC_ENABLED` |
| Instagram | Per-media `views` insight | Confirmed media owner exactly matching selected professional account and `instagram_manage_insights` scope | `PLANNER_INSTAGRAM_METRIC_SYNC_ENABLED` |

The TikTok **POST** to `video/query` is a read-only API query, not a creation/publishing operation. Each adapter uses its platform's official host, constrained identifiers, a bounded timeout and no automatic HTTP resend. Raw OAuth tokens and complete provider payloads must never be stored or returned to the client.

Provider-specific metrics are separated in reporting, UI and AI campaign evidence. `post_media_view`, Instagram views, TikTok views, YouTube views and X impressions are **not comparable** and never combined into a single aggregate. Absent counts remain NULL, not zero. Confirmed publication history, brand membership, immutable evidence and provider-gate checks remain prerequisites. Facebook Page analytics has an additional read-task requirement distinct from Page creation permissions.

**Operational caveats:** Meta, TikTok and Instagram API products/permissions/review vary by application type, region and granted account. Their account ownership and reported response shapes must be tested with each approved live app. The report adapters are code, not provider certification. LinkedIn member/organization analytics is still restricted and unimplemented. No API polling flags have been enabled here.

### 2. Backup verification and disposable restore rehearsal (Batch 5)

Added `scripts/recovery/mysql_recovery.py` and six standard-library unit tests. It can:

1. Stream a MySQL dump to local owner-only gzip file with SHA256 manifest using `mysqldump` and a restricted `--defaults-extra-file` (no passwords in CLI or repository).
2. Verify the dump checksum and gzip stream without a server.
3. Rehearse restore only to a **pre-created, empty** database whose name starts `planner_restore_`, with an explicit operator acknowledgment. The script has no drop/create database operation and refuses non-disposable target names.

This is a **local unencrypted backup artifact**; encrypt and use a separately secured backup vault before offsite retention. Capture and test canonical object-storage snapshots, external queue state, key material and dependent provider grants separately. A source-only fake MySQL rehearsal is not evidence that a real MySQL 8 restore worked. Use a disposable environment and measure RPO/RTO before acceptance.

Examples (review all arguments and keep credential option files `0600`):

```bash
python scripts/recovery/mysql_recovery.py backup --database planner_staging --option-file /secure/mysql-client.cnf --output /secure-local/staging.sql.gz
python scripts/recovery/mysql_recovery.py verify --input /secure-local/staging.sql.gz
python scripts/recovery/mysql_recovery.py rehearse --input /secure-local/staging.sql.gz --target-database planner_restore_20261115 --option-file /secure/mysql-client.cnf --acknowledge-disposable-target
```

### 3. Local environment, CI and migration checker (Batch 5)

- Added previously missing `.env.example` and `.env.testing.example` with **no committed tokens/secrets** and all live publishing / external integration gates default-OFF.
- Fixed a bug in `scripts/check_migration_structure.py` which rejected a valid three-column composite tenant foreign key. The standalone structure guard now passes **137** application-owned tables. This is NOT an executed migration or rollback check.
- Extended existing CI workflow to run full frontend static tests, migration guard, and recovery unit tests in addition to existing PHP/MySQL pipelines.
- Removed duplicated PHP array keys in `config/social_integrations.php` without changing the intended final values.
- Extended React integration controls and source tests to show independent provider metric switches and separate evidence units.
- Added unit tests for Meta Page `ANALYZE` vs `CREATE_CONTENT` task separation and Instagram ownership. Added three factory-backed MySQL test cases for new provider observations.
- **Preserved `composer.lock` and `package-lock.json` byte-for-byte.** The declared `laravel/mcp ^1.2` dependency still needs a reviewed local Composer update; do not delete lockfiles to solve it.

## Checks actually executed here

| Check | Outcome |
|---|---|
| `php tests/Standalone/facebook_page_metric_protocol.php` | PASS: 21 assertions |
| `php tests/Standalone/tiktok_video_metric_protocol.php` | PASS: 15 assertions |
| `php tests/Standalone/instagram_metric_protocol.php` | PASS: 15 assertions |
| `python -m unittest discover -s tests/Recovery -p 'test_*.py' -v` | PASS: 6/6 local/fake-client recovery tests |
| `php scripts/check_repository_boundaries.php` | PASS: 52 Eloquent repositories |
| `php scripts/check_kinship_catalog.php` | PASS: 129 model files, 935 permissions, 4 roles |
| `python scripts/check_migration_structure.py` | PASS: 137 application tables; structural only |
| `find app config database routes tests modules -name '*.php' ... php -l` | PASS: 1,326 PHP file syntax checks |
| `node --test tests/Frontend/*.test.mjs` | 200 PASS / 203 TOTAL; 3 baseline tests unable to import absent `@babel/parser` |
| Real PHPUnit, Composer package installation, MySQL/Storage, provider sandbox, OAuth/MCP browser E2E, recovery on real MySQL | NOT EXECUTED |

**No live post, paid X request, Meta/TikTok/Instagram insights call, Meta/TikTok account login, AI generation, or real email was sent in this environment.**

## Local acceptance plan — user-controlled

1. Inspect both unchanged lockfiles. `composer validate --no-check-publish`, `composer update laravel/mcp --with-all-dependencies`, `composer install`, then `npm ci`. Handle lock/dependency mismatches locally with review; do not automatically delete either lockfile.
2. Configure `.env.testing` from the new safe template, create a disposable MySQL 8 database and generate a test-only APP_KEY. Confirm PHP 8.4 and `pdo_mysql`, `mbstring`, `intl`, `dom`, `xmlwriter`, `fileinfo` extensions and Kinship/Tetranyble Storage package versions.
3. Review and run additive migrations in staging. Execute full PHPUnit Unit, Feature and Integration suites, `npm run build`, then real-browser flows with two tenants and multiple brands, revocations, worker concurrency and lease timeouts.
4. Verify all existing account/provider OAuth scopes on the actual applications, external API spending/quotas, provider ownership matching, signed Storage CDN URLs, media byte integrity/seekability, and status/receipt confirmation. Keep all release flags OFF until proven.
5. Run `php artisan planner:audit-production-release --json`, `php artisan planner:audit-mcp-release --json`, `php artisan planner:verify-storage-media ... --json` with real staging IDs and actual Storage disk. Test REST/UTCP/MCP scopes with revoked, expired, cross-brand and legacy bearer tokens.
6. Use a **real** disposable MySQL backup/restore drill and separately verify object-storage recovery, logs, monitoring, alert email and rollback strategy. Do not use `migrate:fresh` on existing data.
7. Publish and independently verify Pensuh.com privacy, cookie, data-deletion and other legal pages in the **Pensuh marketing website repository**. This archive does not deploy or update those public pages.

## Five-batch status

**Do not present 100% as achieved.** This source candidate improves provider coverage and test/deployment preparation, but unverified external services and unimplemented restricted features remain. Estimates below are subjective and cannot be substituted for acceptance tests:

| Batch | Increment 62 | This candidate | Accepted? / remaining blocker |
|---|---:|---:|---|
| 1. Publishing Engine | 98% | 98% | NO — actual live/provider receipts, CDN media and concurrency tests |
| 2. Content Operations | 98% | 98% | NO — browser E2E, media/approval invariants against MySQL and Storage |
| 3. AI Marketing Intelligence | 90% | 90% | NO — real AI/model evidence, invoice reconciliation and hard monetary budgets |
| 4. Analytics & Automation | 95% | 99% | NO — five source connectors now exist; LinkedIn restricted access and actual provider responses pending |
| 5. Integrations & Production | 94% | 98% | NO — Composer/MCP/CI, real recovery, legal deployment, security/secret rotation and production E2E |
| **Unweighted estimated source scope** | **95%** | **~97%** | **0/5 accepted** |

Never mark a batch `DONE` purely because source lint and offline simulations passed. The user's explicit plan to perform real runtime testing on their own system remains the acceptance boundary.
