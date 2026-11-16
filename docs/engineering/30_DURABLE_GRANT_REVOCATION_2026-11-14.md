# Phase 3 / Increment 30 — Safe OAuth grant revocation

**Date:** 2026-11-14  
**Baseline:** `Planner_Phase3_Meta_Inspection_Retention_Followups_2026-11-14.zip`  
**Status:** implemented; source/static verification only. Real Laravel, MySQL and provider sandbox tests are **not executed**.

## Scope and limits

The remote grant revocation feature is deliberately **disabled by default** via `SOCIAL_REMOTE_REVOCATION_ENABLED=false`. No live revocation was performed.

- Every social provider remains supported for OAuth login/discovery: Facebook, Instagram, LinkedIn, X, TikTok and YouTube.
- Grant **identity tracking** now extends to Facebook/Instagram (same Meta family), TikTok, LinkedIn and X using HMAC-digested provider actor IDs plus the provider app identity. Existing disconnected or legacy grants with missing identity mapping are **not** silently inferred. An additional fail-closed cross-organisation check detects active legacy connection rows sharing the provider actor ID even if they lack grant-group links.
- YouTube's current OAuth grant supplies an empty actor ID and falls back to a *channel*-scoped marker. A Google **user** or project-wide grant cannot reliably be inferred from a YouTube channel ID. Until explicit Google user identity, project-level grant linking, and a multi-client scope audit are available, YouTube grant revocation is **not** programmatically enabled.
- Meta/Instagram, X and LinkedIn grant revocation intentionally finish at `MANUAL_REQUIRED` after confirmation. Their provider revocation semantics still require API-access review and grant-level testing. Do not treat a connection as remotely revoked.
- TikTok is the only implemented remote revocation adapter. It calls the fixed documented Login Kit endpoint; network errors, unknown outcomes and expired in-flight leases result in `UNCERTAIN` and are **not automatically retried**.
- Explicit owner confirmation is distinct from local account disconnection. A provider grant may authorize multiple social accounts and brands.

## New components

- Migration: `database/migrations/2026_11_14_200001_create_provider_grant_revocation_requests.php`
- Model/factory/seeder: `ProviderGrantRevocationRequest`, `ProviderGrantRevocationRequestFactory`, `GrantRevocationDevelopmentSeeder` (opt-in, local/testing only; synthetic inactive PREPARED fixture)
- Persistence contract/implementation: `ProviderGrantRevocationRepositoryContract`, `ProviderGrantRevocationRepository`
- Service orchestration: `ManageGrantRevocations`, `ProcessGrantRevocations`
- Transport: `RemoteGrantRevoker` and `TikTokGrantRevoker`
- Command/schedule: `planner:process-grant-revocations --limit=20`, scheduled each minute (feature flag off by default)
- HTTP: `GrantRevocationController@index/store` plus `ConfirmGrantRevocationController::__invoke`, both web and API, Kinship owner-only middleware.
- React: existing brand social-account directory now distinguishes local disconnect from grant-wide request/confirmation and shows processing/manual/uncertain states.

## Persisted state transitions

`PREPARED` (owner requests impact preview, 15-minute expiry)
→ `READY` (owner confirms unchanged impact; TikTok only)
→ `IN_FLIGHT` (short non-reentrant lease, external call)
→ `ACKNOWLEDGED` (provider HTTP ACK, credentials wiped and linked accounts restricted)

Unsupported provider: `PREPARED` → `MANUAL_REQUIRED`.  
Changes in link membership, no credentials or an unsupported credential state: `READY` → `MANUAL_REQUIRED`.  
Network timeout/error or worker lease expiry: `IN_FLIGHT` → `UNCERTAIN`, with connections restricted; **never automatic retry**.

**`ACKNOWLEDGED` only means the provider returned a success response.** It is not proof of full upstream erasure, deletion of authored content, nor a regulatory-compliance attestation.

## Invariants and concurrency

1. Only an authenticated, verified **organisation owner** with Kinship `social-grant.revoke` can prepare or confirm. Both middleware and domain service enforce the permission. No brand manager privilege escalation.
2. Grant identity uses a keyed digest, never raw OAuth actor IDs in the grant group. Set a stable, strong `SOCIAL_GRANT_ACTOR_HMAC_KEY` before allowing new links. Changing it without migration causes identity duplication.
3. Requests are scoped to a real account in the requested brand; the entire identified grant group is previewed. If any linked connection belongs to another organisation, remote revocation is blocked, without exposing the other tenant's identity.
4. Confirmation compares the live graph with its exact sorted membership digest and count. A new link invalidates the preview. OAuth reconnects are refused while a confirmed/uncertain revocation exists.
5. Credential refresh acquisition is blocked during READY/IN_FLIGHT/UNCERTAIN. The remote worker waits for any already-active refresh or inspection lease among linked connections. No automatic retry follows uncertainty.
6. No token values enter request rows, Eloquent serializers, logs or job payloads. Credential ciphertext is decrypted only inside the isolated worker acquisition, then passed in-memory to the fixed-host adapter.
7. A successful remote ACK atomically marks all in-scope linked provider connections revoked and erases credentials; account capabilities become UNAVAILABLE. The existing Meta privacy deletion callback remains an independent erasure workflow.
8. No remote API calls take place in a database transaction. `ACKNOWLEDGED` is only written after provider confirmation, and an uncertain result retains an auditable unresolved state.

## Local verification to perform before enabling the feature

```bash
composer install --no-interaction --prefer-dist
npm ci
php artisan migrate --env=testing
php artisan test --filter=GrantRevocationWorkflowTest
php artisan test --filter=TikTokGrantRevokerTest
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
php artisan route:list
npm run test:grant-revocation
npm run build
```

Test on a disposable **MySQL 8** database: rollback, partial transaction failures, cross-organisation links, same-organisation multi-brand shared grants, OAuth linking during READY, refresh/inspection race, duplicate commands, crash after provider ACK, stale lease recovery, and data-deletion callback interaction. Verify `kinship:seed` grants for existing tenants with `planner:sync-kinship-catalog --dry-run` before enabling. Confirm 403 for non-owner actor and 409 for cross-tenant link graphs. Test TikTok revocation only with disposable accounts, and **keep `SOCIAL_REMOTE_REVOCATION_ENABLED=false` until signed off**.

## Provider documentation

- TikTok: https://developers.tiktok.com/docs/en/oauth-revoke-a-user-access-token
- Google revocation (intentionally not enabled because it can revoke project-wide scopes): https://developers.google.com/identity/protocols/oauth2/native-app#tokenrevoke

## Risk register / intentionally deferred

- Legacy grant backfill for TikTok/X/LinkedIn; cannot retroactively assert identities without provider-verified facts. Unmapped grants fail closed.
- Google YouTube user identity and project-level grant ownership; Meta, LinkedIn, X remote revocation adapters.
- Authenticated manual reconciliation for `UNCERTAIN`, and privacy officer escalation when the request is related to a statutory deletion. This increment does not falsely complete that workflow.
- Provider-specific remote revocation API agreements, production provider permissions, and full PHPUnit/MySQL/live sandbox execution.
- `pensuh.com` privacy/cookie/terms/deletion-instructions workstream remains OPEN; see `docs/compliance/PENSUH_COM_PRIVACY_AND_LEGAL_PAGES_WORKSTREAM.md`. The live website has not been changed.

## Follow-on milestone

Finish the reconciliation desk (verified provider status and authorized manual resolution, with immutable evidence), live account-level capability probes and tenant-safe historical mapping. Then progress to Storage-backed content drafts and durable publishing. Continue to honor `App/Repository/Contracts` and `App/Repository/Eloquent`, resource controller CRUD + invokable special commands, Kinship routes, factories and seeders, and complete test coverage.
