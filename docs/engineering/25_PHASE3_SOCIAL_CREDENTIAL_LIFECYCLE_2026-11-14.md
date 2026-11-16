# Phase 3 — Social credential lifecycle, safe rotation and reauthorization

**Source authority:** `Planner_Phase3_Credential_Renewal_2026-11-14.zip` (built on six-network OAuth baseline).  
**Status:** implemented in source; PHP lint/static checks executed; no Laravel/PHPUnit/MySQL/live-provider execution in this environment.  
**Date:** 2026-11-14

## Architecture and scope

The application now supports proactive credential renewal for **TikTok, X, YouTube, and LinkedIn if that OAuth application has received a programmatic refresh token**. Facebook and Instagram remain access-only in the current Meta/Facebook Login path: when an access token with a known expiry approaches expiry and there is no refresh token, the connection enters `REAUTH_REQUIRED`. If Meta did not supply `expires_in`, the database cannot proactively infer expiration: online Meta token introspection/long-lived token handling is a separate task.

**Provider publishing remains disabled.** The presence of a valid token does not grant publish scope or bypass approval policies. Social account `DIRECT`/`AUTOMATIC` publishing is not turned on. LinkedIn programmatic renewal is partner-gated.

### Critical reliability properties

1. Due connections are queried by expiry/status/retry time, without reading secrets.
2. `ProviderTokenLifecycleRepository::acquire()` claims a 90-second lease under a MySQL row lock. It loads and decrypts a single refresh token; no HTTP call occurs inside the transaction.
3. `RenewSocialCredentials` delegates to a provider adapter **outside** the database transaction.
4. On successful response, the same transaction persists access/rotated refresh tokens and expiry, enforces TikTok provider identity continuity and no OAuth scope reduction, then clears the lease. If an existing refresh token is reused (e.g., Google), its encrypted value remains stored.
5. On explicit 429, the lease is released and the connection is scheduled for a five-minute retry if the access token remains valid. Invalid grants, ambiguous HTTP outcomes, unexpected errors or stale leases are **not blindly replayed**. They force `REAUTH_REQUIRED` and restrict accounts and capabilities.
6. If a worker crashes between the provider rotating a refresh token and the database committing the new token, the stale lease is deliberately treated as an unknown result requiring a new OAuth login. No distributed transaction with the provider can offer exactly-once rotation.
7. A reconnect after `REAUTH_REQUIRED`, `DISCONNECTED`, or `REVOKED` first deletes invalid old credential ciphertext; a new grant cannot inherit a previously invalid refresh token. Existing connected grants can preserve Google refresh credentials when incremental consent omits one.
8. All response/output from the scheduler and account directory is scrubbed of plaintext tokens. Reconnect reasons are short, server-controlled codes.

### State outcomes

| Case | Connection | Accounts | Next step |
|---|---|---|---|
| Token not due | CONNECTED | Unchanged | Next scheduler pass |
| Refresh succeeded | CONNECTED | Unchanged | New expiry recorded |
| Explicit 429 and access still valid | CONNECTED | Unchanged | Retry in five minutes |
| No refresh token, expired refresh token, invalid grant | REAUTH_REQUIRED | RESTRICTED | Reconnect through OAuth |
| Crash/timeout or stale lease | REAUTH_REQUIRED | RESTRICTED | Reconnect through OAuth |
| TikTok actor changed or scopes reduced | REAUTH_REQUIRED | RESTRICTED | Security review and OAuth reconnection |
| Provider credentials unavailable at scheduler runtime | CONNECTED (brief defer) | Unchanged | Fix environment configuration |
| Account disconnected | DISCONNECTED (when no other active account) | ARCHIVED | No scheduled renewal |

### Files

- `database/migrations/2026_11_14_162001_add_social_credential_refresh_leases.php`: nullable lease, retry, outcome and expiry indexes; no existing column dropped.
- `app/Repository/Contracts/ProviderTokenLifecycleRepositoryContract.php`, `app/Repository/Eloquent/ProviderTokenLifecycleRepository.php`: exclusive claims, transactional updates, reauthorization and capability restrictions.
- `app/Social/Contracts/RefreshableSocialProvider.php`, `app/Social/Data/{TokenRefreshResult,TokenRefreshLease}.php`, `app/Social/Exceptions/TokenRefreshFailure.php`, `app/Social/Support/RefreshTokenResponse.php`: provider-independent contract, sanitized errors and typed outcomes.
- Provider refresh implementations: `TikTokOAuthProvider`, `YouTubeOAuthProvider`, `LinkedInOAuthProvider`, `XOAuthProvider`.
- `app/Services/Social/RenewSocialCredentials.php`, `app/Console/Commands/RenewSocialCredentials.php`, `routes/console.php`: scheduled orchestration.
- `app/Repository/Eloquent/SocialConnectionRepository.php`: connection health read model, reconnection hygiene.
- `resources/js/Pages/Organisations/Brands/SocialAccounts.jsx`: visible reconnect prompt and expiry state.
- Tests: `tests/Unit/Social/TokenRefreshProviderTest.php`, `tests/Integration/Social/TokenLifecycleRepositoryTest.php`, `tests/Frontend/social-token-lifecycle.test.mjs`.

### Commands and rollout

```bash
composer install --no-interaction
php artisan migrate --force                    # only after a reviewed backup and migration plan
php artisan schedule:list
php artisan planner:renew-social-credentials --limit=20 --lead-minutes=20
npm ci
npm run test:social-token-lifecycle
php artisan test tests/Unit/Social/TokenRefreshProviderTest.php
php artisan test tests/Integration/Social/TokenLifecycleRepositoryTest.php
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
npm run build
```

For a fresh isolated MySQL test database, run migrations and the full suite there first. Do not run `migrate:fresh` on existing data. Scheduler must have Laravel's `schedule:run` configured every minute; `onOneServer()` needs **shared atomic-lock capable cache** on multiple instances (Redis or another supported shared store). Worker timeout must remain shorter than the 90-second lease (current adapters use 15-second HTTP timeout). Tune the lease and worker budgets together.

### Factories and seeders

Provider connection and credential factories are retained and now understand optional lease fields. The tests use Eloquent factories, not DB facade inserts. Existing social development seeders remain **opt-in and local/testing-only**; do not seed tokens or active remote connections into production. No additional fake production credentials are introduced.

### Security and known follow-ups

- **Remote revocation is not automatic yet.** Local disconnection erases credentials. Blindly revoking a Google/Meta/TikTok grant can invalidate other channels/brands authorised by the same provider actor; safe revocation needs provider-specific ownership/reference-counting and explicit consent. Design the revocation outbox separately. Do not claim provider-side unlink has occurred.
- Meta expiry introspection and approved long-lived-token exchange, account-level capability probing, and provider-side revocation are next tasks. Provider connection ownership for YouTube multi-channel grants currently falls back to `channel:{first selected id}` when no verified Google user identity is available; before external revocation, correct this with verified Google actor identity, otherwise different channel selections may create separate connections under one provider grant.
- TikTok/X rotated refresh tokens are non-replayable. A network timeout after rotation may require reconnect; this is a deliberate safety tradeoff.
- No token values in Telescope/Sentry/request logs. Disable body logging for token endpoint traffic and enforce redaction in production observability.
- Validate provider scopes, API eligibility, SaaS billing and token semantics in live authorised sandbox applications before production.
- `REAUTH_REQUIRED` is independent of publishing policy approval; reconnecting a provider never grants automatic posting privileges.

### Evidence

Static PHP lint, repository boundaries, Kinship catalogue, migration structure, and frontend tests were executed (see `docs/engineering/PHASE_TRACKER.csv`). The PHP runtime lacks Composer vendor dependencies plus `intl`, `pdo_mysql`, `mbstring` and XML extensions, so PHPUnit/MySQL/provider end-to-end execution remains **PENDING**, per the user's instruction to validate on their own machine later. No pending test is described as passed.

### Official references checked

- https://developers.tiktok.com/docs/en/oauth-refresh-a-user-access-token
- https://developers.tiktok.com/docs/en/oauth-user-access-token-management
- https://learn.microsoft.com/en-us/linkedin/shared/authentication/programmatic-refresh-tokens
- https://learn.microsoft.com/en-us/linkedin/shared/authentication/authorization-code-flow
- https://developers.google.com/identity/protocols/oauth2/web-server
- https://docs.x.com/fundamentals/authentication/oauth-2-0/authorization-code
- https://developers.facebook.com/docs/graph-api/reference/debug_token/
