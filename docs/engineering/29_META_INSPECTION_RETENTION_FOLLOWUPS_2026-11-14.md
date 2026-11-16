# Phase 3 Increment 29 — Meta Grant Inspection and Retention Follow-up Queue

**Date:** 2026-11-14. **Authority:** This ZIP supersedes Phase 27 source. The user will run Laravel/MySQL and provider integration tests later. Do not infer production readiness from lint or source checks.

## Implemented

1. Meta Graph `/debug_token` inspection through a fixed `graph.facebook.com/{configured-version}` host using the Meta app token. Validates application ID, actor identity, validity, expiry, data-access expiry, scopes and granular target IDs. Inspected OAuth tokens are never returned to HTTP consumers, printed, or included in audit logs.
2. Bounded `planner:inspect-meta-connections` background command, scheduled every 15 minutes. A MySQL-backed short inspection lease, encrypted credential read, token-fingerprint confirmation, and transactionally persisted capability change guard concurrent mutation. It never calls the provider API within a DB transaction.
3. Meta `profile.read` capabilities are conservative: every required scope must exist and any provider-scoped target restriction must match the account ID. All `post.publish` / `media.publish` remain UNAVAILABLE. Failed inspection disables existing capabilities and schedules retry; invalid grant forces reauthorization.
4. `privacy_retention_followups` normalized table with one unique due reminder per retained-legal decision; scheduled `planner:queue-privacy-retention-reviews`. Only organisation owners with Kinship `privacy-deletion.review` can list or acknowledge. Owner service checks tenant and role again. An acknowledgement is recorded separately from the immutable legal decision; it DOES NOT certify deletion or extend legal authority.
5. Factory for follow-ups and an opt-in local/test-only seeder. PHPUnit unit, feature and MySQL integration test source for Meta inspection, grant-scoping, token handling and retention queue idempotency.
6. React/Inertia owner privacy page now includes due retention reminders with acknowledgement notes. Existing privacy review/Meta deletion callbacks unchanged.

## Schema changes (ADDITIVE)

- `2026_11_14_190001_add_meta_inspection_lease.php`: six inspection fields and a due index on `provider_connections`. No OAuth secrets are stored in these new fields.
- `2026_11_14_190002_create_privacy_retention_followups.php`: typed foreign keys, due/status/actor/note fields, uniqueness on review decision.

## Architecture and invariants

- `App/Repository/Contracts` and `App/Repository/Eloquent` only for persistence; domain logic in `App/Services/Social`.
- No new nonstandard resource controller methods; command routes use invokable controllers.
- Kinship permission at HTTP boundary (`kinship.permission:privacy-deletion.review`) and active organisation-owner validation inside the service.
- User data retention is separate from token revocation and local account disconnect.
- No auto remote revocation of Meta/Google/TikTok/X/LinkedIn grants. **Reason:** shared OAuth grants may span Pages, channels, brands and organisations; legacy links may be incomplete and the existing local disconnect erases credentials. A safe remote revoke requires a future two-phase lease/outbox + complete grant-ownership graph before any external call. Meta provides `/debug_token` but it is not a revocation endpoint. Google revocation can invalidate every scope in the project (see source).
- No automatic publishing. Capability inspection cannot enable publishing by accident.
- One-shot legal retention reminders are implemented; recurring retention renewal and disposition changes require a separate versioned legal decision model and are NOT implemented. An acknowledgment only records review attention.
- Manual review/erasure inventory for object storage, logs, archived backups, privacy policy publication on pensuh.com, and cross-platform privacy workflows remain open.

## Verification commands (disposable environment)

```bash
composer install
php artisan migrate --force
php artisan route:list
php artisan test --filter=MetaTokenInspectorTest
php artisan test --filter=MetaCapabilityInspectionRepositoryTest
php artisan test --filter=PrivacyRetentionFollowupsTest
php artisan test --filter=PrivacyRetentionEndpointsTest
php artisan test
php artisan planner:inspect-meta-connections --limit=10 --minutes=60
php artisan planner:queue-privacy-retention-reviews --limit=25
npm ci && npm run build
```

**Do not run** `migrate:fresh` on an existing populated database. Check `config/social_integrations.php` Meta app credentials, Graph version, APP_KEY, and scheduler single-server locking before activating the inspections. Use test-only synthetic provider credentials during initial validation. Inspect HTTP traffic redaction at the monitoring layer because the `/debug_token` query necessarily includes the user access token.

## Acceptance and failure tests still required at runtime

- Valid Meta token, wrong-app token, changed actor, expired data access, missing scopes, granular Page/Instagram target restriction.
- Concurrent inspection workers; stale leases; rotated credentials between HTTP response and DB commit; deletion callback processed during inspection; network timeout and retry.
- Privacy queue deduplication, future retention dates excluded, cross-organisation isolation, duplicate acknowledgement rejected, immutable legal decision unchanged.
- Live Meta Graph permissions/task interpretation, rate limits, health; Meta app review and Page task checks. Offline tests do not prove provider access.
- Browser/React screen and full Vite build, CI on MySQL 8 with Kinship and Tetranyble Storage.

## Current source-level validation

Record results in `docs/engineering/TEST_MATRIX.csv` and `CONTINUE_HERE.md`. Do not label PHPUnit/MySQL/live OAuth tests as passing until the user provides their actual results.

## Pensuh.com required legal updates

Carry forward `Pensuh_Website_Privacy_Compliance_Workstream_2026-11-14.md`. Update privacy policy, cookie policy, data deletion instructions, contact/demo consent, relevant terms and security pages and sitemap. **Not published or changed by this increment.** Have legal/privacy review before release.

## Next engineering milestone

A complete per-provider authorization-grant graph and two-phase revocation protocol (lease, provider call outside transaction, reconciliation, retry, scoped credential erasure, audit) with provider-specific revocation adapters. Follow with live capability probes including Meta Page tasks and Instagram Page linkage. Do not use `POST /debug_token` as a revocation path.
