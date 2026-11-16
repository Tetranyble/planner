# Phase 3 — Social connection foundation (2026-11-14)

**Status: IMPLEMENTED, NOT RUNTIME-VERIFIED.** New baseline: `Planner_Phase3_Social_Connection_Foundation_2026-11-14.zip`. Source: previous Phase 2 claim governance ZIP. Preserves Laravel + Inertia React/shadcn theme, Kinship workspace grants, Tetranyble Storage, six CRUD/controller methods plus invokables, `App/Repository/Contracts` + `App/Repository/Eloquent`, and factories/seeders. No new Composer or NPM dependencies.

## Shipped scope and API contract

- TikTok **Login Kit v2**: static callback, `user.info.basic` authorization; OAuth code exchange and identity verification with `GET /v2/user/info/`. Publishing is **ASSISTED** only — no unapproved direct publishing.
- Google/YouTube OAuth: `youtube.readonly` offline consent; token exchange and `channels.list(mine=true)` discovery. Video publishing is **UNAVAILABLE** until a separately approved `youtube.upload` grant and publishing subsystem exist.
- Provider registry: hardcoded allowlist, environment-backed credentials, available/unconfigured status. Unknown provider is 404, unconfigured provider is 503; no dynamically constructed external hostnames.
- OAuth start: `POST /organisations/{organisation:uuid}/brands/{brand:uuid}/social-connections/{provider}/start`, invokable controller, `kinship.role:owner,manager` + `kinship.permission:social-account.connect` + verified organisation context. Inertia requests use 409 `Inertia::location` for external redirect; ordinary form requests use 302/redirectAway.
- OAuth callback: `GET /integrations/social/{provider}/callback`, session-authenticated + verified, fixed provider-specific URI, 10-minute one-time state stored as SHA-256 digest, callback bound to initiating user, organisation and brand. State is consumed atomically before provider HTTP operations; replay and wrong-user attempts return 410. On authorization failure users initiate a fresh flow.
- Account directory: `GET /organisations/{organisation:uuid}/brands/{brand:uuid}/social-accounts` (Inertia) and `GET /api/v1/organisations/{organisation:uuid}/brands/{brand:uuid}/social-accounts` (JSON) with Kinship `social_account.index` and service-level brand member permission checks; no secrets in API results.
- Local disconnect: `DELETE /organisations/{organisation:uuid}/brands/{brand:uuid}/social-accounts/{accountUuid}` with `social-account.disconnect`, brand scope; archives account, appends status event, and erases encrypted local credentials when the provider connection is no longer used. **This does not revoke provider-side OAuth grants**; users must revoke provider consent separately until revocation adapters are implemented.
- Scopes, capabilities and denied operations are tracked in normalized social-account tables. Tokens remain on server only; access/refresh secret reference columns point to the private encrypted database credential table.

## Migrations and integrity

Additive migration: `2026_11_14_160001_create_social_oauth_attempts_table.php`.

1. `social_oauth_attempts`: organisation/brand/user, provider, `state_digest` (unique), original redirect URI, expiry, consumed timestamp. Composite brand FK; single-use under row lock. Raw state is never stored.
2. `provider_connection_credentials`: organisation + connection composite FK; encrypted access/refresh ciphertext, optional refresh expiry. No API serialization. Unique per connection. Encrypted via Laravel `Crypt` (`APP_KEY`); production KMS-backed vault can replace the repository without changing services.
3. New unique identity index `provider_connections(organisation_id, provider, external_actor_id)`; check existing data for duplicates before applying. MySQL permits multiple NULL actor IDs; completed OAuth records always have non-empty actor identity.

**Preflight for existing data** (MySQL):

```sql
SELECT organisation_id, provider, external_actor_id, COUNT(*) AS duplicates
FROM provider_connections
WHERE external_actor_id IS NOT NULL
GROUP BY organisation_id, provider, external_actor_id
HAVING COUNT(*) > 1;
```

Ensure `APP_KEY` is securely backed up, not regenerated on a live instance without decrypt/re-encrypt of credentials, and ensure APP_URL uses HTTPS in production. Never store tokens in logs, session flash, browser props or Tetranyble Storage media.

## Architecture and operational policy

- `App/Social/Contracts/SocialOAuthProvider` + `TikTokOAuthProvider`, `YouTubeOAuthProvider`, `SocialProviderRegistry` isolate provider protocols from application services. `OAuthGrant` is an in-memory sensitive DTO.
- `App/Repository/Contracts/SocialConnectionRepositoryContract`, `ProviderCredentialRepositoryContract`, plus Eloquent implementations exclusively own persistence. `ManageSocialConnections` orchestrates authorize → state → external API → transaction → audit. Org→brand row locks serialize account assignment, and permissions are rechecked under lock (including non-HTTP tool callers).
- No network traffic inside persistent transactions: provider exchange/discovery occur after state consumption and before transactional link+vault+audit. This sacrifices retrying a failed callback safely (must start again) to avoid replay/double-use and broad external side effects.
- One social account identity may be assigned to only one brand within its organisation (existing unique constraint). Reassignments require an explicit future workflow, not silently moving accounts. A provider connection can cover more than one account; last-account disconnect erases the local secret record.
- `App/Models/ProviderConnectionCredential` hides both ciphertext fields, and `ProviderConnection` hides its credential refs. Factory data uses synthetic credentials only. No duplicate auth/role/storage package introduced.
- Four pre-existing social seeder classes are now filled and idempotent; `SocialConnectionsDevelopmentSeeder` orchestrates them and is **opt-in, local/testing only**. The default production seeder never creates fictional connected accounts.
- Provider access restrictions: TikTok public direct-post API requires approval/audit; internal private tools may be ineligible. YouTube upload requires a new scope, separate review and quota handling. Do not expose an automatic-publish ability from these read-only OAuth grants.

## Exact configuration and smoke steps (local or staging)

```env
APP_URL=https://YOUR_STAGING_DOMAIN
TIKTOK_CLIENT_KEY=...
TIKTOK_CLIENT_SECRET=...
GOOGLE_OAUTH_CLIENT_ID=...
GOOGLE_OAUTH_CLIENT_SECRET=...
```

Register these **exact static** redirect URIs in the respective developer dashboards:

- `https://YOUR_STAGING_DOMAIN/integrations/social/tiktok/callback`
- `https://YOUR_STAGING_DOMAIN/integrations/social/youtube/callback`

Run `php artisan migrate` (not `migrate:fresh` on an existing DB); complete migrations first on a disposable MySQL schema. The Kinship catalogue discovers two new models automatically only when `kinship:seed` or the project's catalogue-sync command runs; the existing special permissions (`social-account.connect`, `social-account.disconnect`) are already defined and granted to manager/owner. Verify role grants and do not accidentally grant provider-credential read permissions to lower roles. Once authenticated, visit `/organisations/{organisationUuid}/brands/{brandUuid}/social-accounts` and run a real provider OAuth grant in staging with approved accounts. Do not publish videos during this test.

## Test evidence and limitations

Authored:

- `tests/Unit/Social/ProviderContractsTest.php` (redirect parameter/scope, foreign TikTok identity rejection, YouTube read-only capability).
- `tests/Feature/Social/SocialOAuthConnectionTest.php` (session+Kinship, token secrecy, state replay/wrong actor/expiry, cross-brand conflict, disconnect, no added credentials from unconfigured provider, YouTube read-only, cross-org denial). External HTTP requests are faked.
- `tests/Integration/Social/SocialConnectionFactoriesTest.php` (factory-FK relationships, cross-tenant factory guard, idempotent development seeding, unique provider actor index).
- `tests/Frontend/social-connections-architecture.test.mjs` (five route, auth, secret and capability constraints).

Executed in container: PHP syntax, standalone repository/Kinship guards, frontend architecture/theme/JSX parser checks. **Not executed:** Laravel PHPUnit suites, actual MySQL migration or OAuth callbacks, provider live sandbox, provider app review, browser E2E, npm production build. Container lacks Composer vendor tree and essential PHP extensions/PDO MySQL. User requested deferring runtime validation. Do not mark these as passed.

Run in an environment with dependencies:

```bash
composer install --no-interaction
npm ci
php scripts/check_repository_boundaries.php
php scripts/check_kinship_catalog.php
php artisan migrate:fresh --env=testing --force # disposable database ONLY
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
npm run test:social-architecture
npm run build
```

## Next implementation milestone

Phase 3 continuation: provider token refresh with lease/rotation safeguards, revocation and reauthorization states, Facebook Pages/Instagram integration after developer app review, per-account capability probing and sync jobs, webhook verification. Implement assisted TikTok posting as a separate approved content workflow. **No automatic publication, metrics ingestion, background sync or full UTCP/MCP social tools are implemented in this slice.** Preserve policies/roles/tenant isolation, add explicit HTTP contract tests for each provider, and keep migration/API/permission documentation in sync.

## External verification references (November 2026)

- TikTok OAuth: https://developers.tiktok.com/docs/en/oauth-get-a-user-access-token
- TikTok Login Kit: https://developers.tiktok.com/docs/en/login-kit-overview
- TikTok user info: https://developers.tiktok.com/docs/en/tiktok-api-v2-get-user-info
- Google web server OAuth: https://developers.google.com/identity/protocols/oauth2/web-server
