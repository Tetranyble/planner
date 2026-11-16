# Increment 62 — Storage v3 integration, safe publishing halt, usage cost evidence and alert delivery

Date: **2026-11-15**. Full-source continuation of Increment 61. **Target source coverage ~95% is an engineering estimate only**: **0/5 batches accepted**; Laravel/MySQL/Composer/real Storage/SMTP/provider/browser testing remains unexecuted in this container. Do not enable publishing as a consequence of this source checkpoint.

## 1. Tetranyble Storage v3 adapters (Batches 1/2/5)

Compared with actual published v3.0.0 signatures:

- `Tetranyble\Storage\Modules\Storage\Application\Contracts\FileSystemContract::readStream(string $path, ?Disk $disk)` and `signedUrl(string $path, ?Disk $disk, int $ttlMinutes, array $options)`.
- Package `Media` fields `workspace_id`, `path`, `disk`, `size`, `sha256`, `mime_type`, temporary/archive/quarantine state.
- Source references: https://raw.githubusercontent.com/Tetranyble/storage/v3.0.0/src/Modules/Storage/Application/Contracts/FileSystemContract.php and https://raw.githubusercontent.com/Tetranyble/storage/v3.0.0/src/Modules/Media/Infrastructure/Persistence/Eloquent/Models/Media.php.

Added `CanonicalStorageMediaAccess`, `TetranybleCanonicalVideoChunkSource` and `TetranybleMediaDistributionUrlResolver`. Their methods verify workspace-scoped media, canonical SHA-256 and metadata, prohibit arbitrary paths, use only package-owned interfaces, refuse nonseekable stream offsets, limit chunk size to 8 MiB, and refuse unapproved HTTPS distribution hosts. The signed external URL is never returned to the browser, persisted as a new content field, or logged by the adapters.

Bindings are conditional on `PLANNER_STORAGE_V3_ADAPTER_VERIFIED` **default false**; they otherwise bind existing `Unavailable*` implementations. Existing media and YouTube release switches are still OFF. Do not set the flag just because source lint passes: verify installed `tetranyble/storage` version, actual backend seekability, canonical-byte SHA-256, provider-fetch expiry and exact CDN hostname in a disposable environment. The optional signed URL resolver also requires `PLANNER_MEDIA_DELIVERY_ORIGIN_VERIFIED=true` and `PLANNER_MEDIA_DISTRIBUTION_HOSTS` as an explicit comma-separated **exact-host** allowlist. The signed URL TTL is 60 minutes; provider-fetch runtime compatibility and redirect behavior still require E2E tests. The v3 source API is confirmed; actual installed package and runtime are not.

New manual verifier: `php artisan planner:verify-storage-media {organisation_id} {media_asset_id} --max-mib=256 --json`. It hashes bounded whole media by reading 4 MiB ranges from the installed package stream, never sends provider I/O, and returns sanitized pass/fail. It requires the adapter flag in a test/staging environment. Refuses over-limit objects instead of silently checking a partial digest.

**Do not enable live media publishing until** the canonical byte source passes on the actual configured disk, signed URLs are provider-fetchable without exposing private objects, Meta/TikTok/Google app scopes and approvals are attested, Laravel integration/lease tests pass, and uncertainty reconciliation is exercised. A nonseekable disk intentionally fails closed rather than requesting the full file repeatedly. Test with the exact storage backend you will deploy.

## 2. Single default-ON emergency publishing stop (Batches 1/5)

`PLANNER_EMERGENCY_PUBLISH_STOP=true` by default. Text, Instagram/TikTok media, and YouTube workers all require its value to be false **in addition to** their existing independent production/provider release gates. The YouTube operator arming flow also checks it. Disabling this stop requires explicit config deployment/reload for each worker: it is **not** a zero-latency central runtime kill switch across already-loaded queue processes, and it does not interrupt requests already sent. To stop workers, also stop the scheduler/queue processes as part of incident response. No existing publication attempts or evidence are removed by the stop.

## 3. Provider usage cost *estimate* (Batch 3)

Added nullable `estimated_micro_usd` and `cost_rate_revision` on the brand AI reservation. `VerifiedTokenCostEstimate` accepts only exact non-negative integer provider token counters plus an **operator-configured** per-model rate map and rate revision. It uses bounded integer arithmetic and captures the cost snapshot when the existing reservation settles; unpriced generations remain NULL, not $0. This is an estimate from operator rates, **NOT a provider invoice nor a guaranteed budget**. API usage now exposes priced, unpriced and observed micro-USD totals separately. Configuration:

```
PLANNER_AI_COST_RATE_REVISION=your-approved-contract-revision
PLANNER_AI_COST_RATES_JSON={"your-model-id":{"input_micro_usd_per_million":12345,"output_micro_usd_per_million":67890}}
```

The values above are illustrative placeholders, **not actual model pricing**. Use the exact model identifier and approved pricing schedule. Never infer contract prices from AI output or treat unreported usage as zero. Existing per-brand daily request limits are unchanged; this does not implement a hard monetary spend cap.

## 4. Operational email alerts with non-replayable uncertainty (Batch 4/5)

Added additive composite-key, brand-scoped alert-mail outbox and an extra unique event-scope index. Only OPENED/REOPENED alert evidence can trigger notices. Recipient is the organisation's verified original creator **only while their organisation membership is still active**; no cross-tenant emails. `PLANNER_OPERATIONAL_ALERT_MAIL_ENABLED=false` by default. `planner:send-operational-alerts --limit=30` is scheduled every five minutes but no-ops until enabled. Queued events are unique per event/user, claimed with MySQL SKIP LOCKED and fenced by a lease owner. Mail provider failure or an expired in-flight lease goes to **UNCERTAIN** for manual review; the system cannot prove non-delivery and never blindly resends. The brand safety React panel displays queued/sent/uncertain email counters. SMTP/recipient privacy and opt-in consent must be checked in your environment. This is a minimal verified-creator notification, not a general subscription/notification preference system.

## 5. Production gate

Added `planner:audit-production-release --json [--strict]` to report sanitized HTTPS, debug, application key, PHP extensions, database tables, queue driver, package interface, MCP installation and unsafe publication switches. It never certifies legal compliance or provider access and never performs provider API I/O. Also includes the existing `planner:audit-mcp-release --json` and publication/Meta grant gates. A passing audit is only a preflight, not E2E proof.

## Execute locally (disposable MySQL 8, PHP 8.4 and installed Composer packages)

**Do not delete** `composer.lock` or `package-lock.json` automatically. The old Composer lock was already out of sync with `laravel/mcp` from Increment 61 and remains untouched here. On your machine review changes and run:

```bash
composer validate --no-check-publish
composer update laravel/mcp --with-all-dependencies
composer install
npm ci
php artisan migrate --force         # only when the target DB is the intended disposable/staged DB
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
node --test tests/Frontend/*.test.mjs
npm run build
php artisan planner:audit-production-release --json
php artisan planner:audit-mcp-release --json
# On a disposable staging environment, with Storage v3 adapter verified and a test media asset:
php artisan planner:verify-storage-media 1 1 --max-mib=256 --json
```

Never run migrate:fresh on an existing database. Run `php artisan migrate:status`, review FK prerequisites and use add-only migrations. Replace the sample organisation/asset IDs with actual data. Enable alert email only after SMTP dry-run, recipient authorization, and uncertain-send review tests. Do not enable media/outbound publishing until provider approval and full runtime/browser QA.

## Actual validation performed in this container

- Portable PHP AI cost arithmetic and exact-origin rules: **passed**.
- New focused frontend static safety suite: **4/4 passed**.
- Full frontend suite: **191/194 passed**; same 3 missing `@babel/parser` dependency failures from baseline.
- Controller convention: **4/4 passed**; Kinship catalog and repository boundary guard **passed**.
- PHP syntax sweep: see terminal check recorded in this handoff, includes newly added migrations, models, services, commands, factories and tests.
- Composer install, Laravel PHPUnit, MySQL migrations/leases, Storage stream runtime, SMTP, remote provider posting and production E2E: **NOT EXECUTED**.
- No actual social post sent, no alert email sent, no remote content made public.

## Current source coverage estimates — NOT production acceptance

| Agreed batch | Increment 61 | Increment 62 estimate | Accepted? |
|---|---:|---:|---|
| 1. Publishing Engine | 95% | **98%** | NO: actual provider & Storage runtime |
| 2. Content Operations | 95% | **98%** | NO: full media/approval/browser E2E |
| 3. AI Marketing Intelligence | 82% | **90%** | NO: invoicing, monetary cap, real model tests |
| 4. Analytics & Automation | 88% | **95%** | NO: other provider ingestion & mail/runtime QA |
| 5. Integrations & Production | 90% | **94%** | NO: MCP install/client OAuth, backups, secrets, deployment E2E |
| **Unweighted source estimate** | **90%** | **95%** | **0/5 accepted** |

These are subjective scope-completion estimates, not PHPUnit pass percentages, invoiced prices, security certification, real provider validation or evidence of a deployable system. The remaining scope includes additional provider analytics, media distributor/provider interoperability, delegated MCP OAuth, production backups/restore drills, secrets rotation, Pensuh.com privacy/legal deployment, and real testing on the user's environment.
