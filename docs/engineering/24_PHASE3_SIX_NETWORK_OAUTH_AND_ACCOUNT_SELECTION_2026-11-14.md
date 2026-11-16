# Phase 3 — Six-network OAuth & account selection

**Date:** 2026-11-14  
**Scope:** Social account connection, discovery, consent, and safe brand assignment. **Does not authorize publishing.**  
**Status:** Implementation source complete; PHP lint, static architecture, and frontend tests run; PHPUnit/MySQL/live OAuth still pending user runtime validation.

## Network matrix

| Network key | Account discovery | OAuth & consent | Publish capability at this phase | App prerequisites |
| --- | --- | --- | --- | --- |
| `facebook` | Facebook Pages available to the signed-in user | Meta Facebook Login; `pages_show_list`, `pages_read_engagement` | `post.publish=UNAVAILABLE` | Meta app; Facebook Login; Page role; separate review and `pages_manage_posts` for later publishing |
| `instagram` | Professional Instagram accounts linked to accessible Pages | **Instagram API with Facebook Login** (NOT Instagram Login): above scopes plus `instagram_basic` | `media.publish=UNAVAILABLE` | Professional IG account linked to Facebook Page; Meta app and approved permission |
| `linkedin` | Authenticated member; Company Pages additionally gated by `LINKEDIN_ORGANIZATIONS_ENABLED=true` | OIDC `openid profile email`; Company Pages require approved `rw_organization_admin` | `post.publish=UNAVAILABLE` | LinkedIn app, OIDC product, restricted Community Management access for Company Pages |
| `x` | Authenticated X user | OAuth 2.0 Authorization Code **PKCE/S256**: `tweet.read users.read offline.access` | `post.publish=UNAVAILABLE` | X developer app, paid/allowed API access; `tweet.write` for future publishing |
| `tiktok` | Creator profile | TikTok Login Kit `user.info.basic` | `video.publish=ASSISTED` | TikTok developer app and separate Direct Post eligibility audit |
| `youtube` | Authenticated channel(s) | Google OAuth `youtube.readonly` | `video.publish=UNAVAILABLE` | Google Cloud OAuth app; later `youtube.upload` consent for publishing |

The backend controls capability modes rather than assuming an OAuth grant implies upload, video, analytics, or post permissions. **Facebook personal profile publishing is not supported.** Instagram personal accounts without the required professional account setup are not supported by the selected Meta API path. The optional LinkedIn Company Page path is intentionally disabled until the application receives restricted access; member connection is functional independently.

## Implementation inventory

### Provider architecture

- `app/Social/Contracts/SocialOAuthProvider.php` is the single interface for authorize/exchange/discover/capabilities. Exchange accepts the originating OAuth `state` only for providers requiring PKCE; existing TikTok and YouTube implementations retain compatibility.
- `app/Social/SocialProviderRegistry.php` exposes the canonical allowlist of all six provider keys. Routes, configuration and UI use this allowlist; callers cannot supply arbitrary OAuth URLs or provider classes.
- `MetaOAuthClient.php` shares Meta OAuth and Page discovery. It validates the authenticated `/me` user ID and separately verifies **granted permissions** through `/me/permissions`. It uses fixed Meta hosts and bounded cursor pagination, and never follows a provider-supplied arbitrary paging URL.
- `FacebookOAuthProvider.php` / `InstagramOAuthProvider.php` reuse Meta transport with distinct consent scopes. Instagram only includes Pages with a linked professional account.
- `LinkedInOAuthProvider.php` implements OIDC userinfo verification and an optional organization ACL lookup with versioned LinkedIn API headers.
- `XOAuthProvider.php` uses PKCE S256 with a verifier derived by HMAC from the one-time, server-validated 64-character state using `APP_KEY`. No plaintext PKCE verifier is stored. This design assumes a stable APP_KEY across the authorization round-trip.
- Existing TikTok and YouTube adapters remain intact.

### Multi-account safety — explicit selection

OAuth callbacks that discover more than one eligible account **do not connect any account automatically**. Instead:

1. The callback consumes the original hashed OAuth state (ten-minute one-use state, same initiating user).
2. It creates a `social_oauth_selections` record containing encrypted grant/candidate payloads, scoped to `organisation_id`, `brand_id`, and `user_id` for ten minutes.
3. The authenticated actor opens `GET /organisations/{organisation:uuid}/brands/{brand:uuid}/social-selections/{selectionUuid}` to review only non-secret candidate metadata.
4. The user explicitly selects account IDs and posts them to `POST /.../social-selections/{selectionUuid}/confirm`.
5. Kinship edge checks and service-level brand access are revalidated, the selection row is locked, selected identities must match the original consented accounts, and repository-managed `connect()` + audit + consumption occur transactionally.
6. Tokens and candidate ciphertext are overwritten with encrypted tombstones on success; consumed selections reject replay. Expired/consumed rows are cleaned by `planner:prune-social-oauth-selections` every ten minutes with a one-hour retention buffer.
7. One-account OAuth results use the existing direct connection path. A cross-brand claim still returns conflict rather than silently moving an existing account.

In particular, **a login that administers both Pensuh and FlymyMall must not implicitly assign both Pages to Pensuh**.

This is one new additive migration: `2026_11_14_161001_create_social_oauth_selections_table.php`. Tenant scope is enforced by composite foreign key. The factory `SocialOAuthSelectionFactory` produces only synthetic credentials, and pending grants are never seeded into production.

### Repository / HTTP contracts

- `App/Repository/Contracts/SocialOAuthSelectionRepositoryContract` and `Eloquent/SocialOAuthSelectionRepository` own persistence and short-lived encryption. All new service persistence goes through these classes.
- `ManageSocialConnections` remains the single application service, so future REST/UTCP/MCP adapters will use the same authorization and domain logic.
- `SocialOAuthSelectionController@show` is the standard CRUD read method. `ConfirmSocialOAuthSelectionController::__invoke` is a dedicated command, per controller conventions. All routes use Kinship and organisation context at the edge; service checks remain mandatory for non-HTTP execution.
- Account selection React/Inertia page uses the existing theme tokens and shadcn button. No API response exposes tokens or ciphertext.
- `EncryptedProviderCredentialRepository` now permits OAuth access-only grants without a refresh token. This is essential for Meta/LinkedIn. These grants require reconnect/renewal before expiration; token rotation and revocation workers remain a **separate unfinished task**.
- No other model factories or seeders were replaced; original development social seeders still provide synthetic, disconnected TikTok fixtures only.

## Environment variables

```env
APP_URL=https://planner.example
APP_KEY=base64:... # generate via php artisan key:generate; never overwrite an active production key casually
META_APP_ID=
META_APP_SECRET=
META_GRAPH_VERSION=v27.0
LINKEDIN_CLIENT_ID=
LINKEDIN_CLIENT_SECRET=
LINKEDIN_API_VERSION=202610
LINKEDIN_ORGANIZATIONS_ENABLED=false
X_CLIENT_ID=
X_CLIENT_SECRET=
TIKTOK_CLIENT_KEY=
TIKTOK_CLIENT_SECRET=
GOOGLE_OAUTH_CLIENT_ID=
GOOGLE_OAUTH_CLIENT_SECRET=
```

Register these redirect URIs exactly in each provider's developer portal, with the real application origin:

```text
https://planner.example/integrations/social/facebook/callback
https://planner.example/integrations/social/instagram/callback
https://planner.example/integrations/social/linkedin/callback
https://planner.example/integrations/social/x/callback
https://planner.example/integrations/social/tiktok/callback
https://planner.example/integrations/social/youtube/callback
```

For both Facebook and Instagram, the two callback URIs must be approved in the same Meta app used by this implementation. All production callbacks require HTTPS. For staging, use a registered, real staging domain and separate provider app credentials where appropriate.

**Operational dependencies:** provider app review, scopes granted to the developer application, Page/organization administrative permissions, API billing/quotas, correct domain/redirect settings, and a stable `APP_KEY`. API product approvals are not interchangeable with Laravel/Kinship permissions.

## Validation & test evidence

**Executed:**
- PHP syntax checks across application, config, database, routes and tests.
- `php scripts/check_repository_boundaries.php` — pass.
- `php scripts/check_kinship_catalog.php` — pass.
- `python scripts/check_migration_structure.py` — pass for 95 application-owned tables after the new migration.
- Frontend/architecture test suites (theme, onboarding, memberships, brand governance, controller conventions, social architecture); 43 focused checks passed.

**Authored, NOT executed:**
- `tests/Unit/Social/SixNetworkProviderContractsTest.php` — source-faked provider authorization, identity verification, scopes, capability restrictions, PKCE.
- `tests/Feature/Social/SixNetworkOAuthConnectionTest.php` — real DB/Kinship flows with faked provider transport, access-only tokens, explicit account selection, expiry/replay, and provider-specific account linking.
- `tests/Integration/Social/SocialOAuthSelectionRepositoryTest.php` verifies factory-backed MySQL tenant FKs, encryption, and retention pruning.
- Existing `tests/Feature/Social/SocialOAuthConnectionTest.php` and the MySQL factory suite remain required.

Run on disposable MySQL with installed Composer/node dependencies:

```bash
composer install --no-interaction
npm ci
php artisan migrate --env=testing --force  # do not run against real production data
php artisan test tests/Unit/Social
php artisan test tests/Feature/Social
php artisan test tests/Integration/Social
php artisan route:list --path=integrations/social
npm run test:social-architecture
npm run build
php artisan schedule:list
```

**Runtime risk not closed:** This container lacks Composer vendor dependencies, PDO MySQL and required PHP extensions. Real OAuth provider sandbox, APP_URL/redirect checks, business permissions, expiration handling, account-owner roles, API billing, and browser E2E must be validated by the user on their environment. The unit/feature tests are authored but cannot be claimed passed here.

## Remaining release blockers / follow-up work

1. **Provider token refresh, rotation and revocation:** Meta long-lived user tokens/Page tokens, LinkedIn limited refresh availability, X refresh rotation, TikTok and Google renewal, reauthorization state and scheduled expiry alerts. Store key material only server-side, reconcile per provider. Account status/connection status must reflect expiry/revocation.
2. **Per-account capability probing** after approval, rather than relying on global static scopes. Do not mark a network `DIRECT` without proven access and a completed publisher implementation.
3. **Explicit linked account selection** is implemented, but real provider multi-Page and Company Page permission testing remains open.
4. **Media and publishing execution**: use Tetranyble Storage for files, exact approved content-version hashes, durable outbox jobs, idempotency/reconciliation for ambiguous external responses, provider-specific uploads/posts. Never claim exactly-once remote posting.
5. **Company Page access**: LinkedIn approval can be denied; keep the OIDC-only member route available until approved. Instagram direct Business Login is an additional integration route that could be supported separately if required.
6. **Security operations**: provider webhooks signature validation, periodic credential/pruning and secret rotation, quota/billing alerting, bounded provider HTTP retry/error mapping, lifecycle audit coverage, and browser accessibility E2E.

## Official verification references consulted November 14, 2026

- Meta Page reference: https://developers.facebook.com/docs/graph-api/reference/page/
- Meta Page feed & posting permissions: https://developers.facebook.com/docs/graph-api/reference/page/feed/
- Instagram official Meta API collection: https://www.postman.com/meta/instagram/documentation/6yqw8pt/instagram-api
- LinkedIn OIDC / userinfo: https://learn.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/sign-in-with-linkedin-v2
- LinkedIn Community Management: https://learn.microsoft.com/en-us/linkedin/marketing/community-management/community-management-api-migration-guide
- X API OAuth/PKCE: https://docs.x.com/fundamentals/authentication/oauth-2-0/authorization-code
- X API pricing: https://docs.x.com/x-api/getting-started/pricing
- TikTok Login Kit: https://developers.tiktok.com/docs/en/login-kit-overview
- Google OAuth: https://developers.google.com/identity/protocols/oauth2/web-server
