# Phase 3 — Meta data-deletion callback, consent fence and provider-derived erasure

**Status:** IMPLEMENTED / RUNTIME-UNVERIFIED. Source baseline: `Planner_Phase3_Credential_Renewal_2026-11-14.zip`. Date: 2026-11-14.

## Purpose and scope

Meta supplies an **app-scoped user ID** in the authenticated `signed_request` body. It does **not** supply an organisation, brand, Page ID, or Instagram professional account ID. The same Meta app authorizes both the Facebook and Instagram integrations in Planner. Signed deletion requests therefore cross internal tenant boundaries by design, and use the **Meta application secret** as authentication, not Kinship, browser cookies, or a guessed tenant.

The design distinguishes:

1. **Local disconnect:** current brand stops using an account; do not revoke an OAuth grant belonging to other accounts.
2. **Provider deauthorization:** restrict all accounts dependent on a grant; provider-side revocation is not enabled in this increment.
3. **Provider data deletion:** receive a signed Meta deletion request, return a confirmation URL/code, immediately erase provider credentials and restrict connections, then run a retryable data-erasure workflow with truthful public status.

Other networks must have individually researched deletion/consent requirements; DO NOT apply Meta signed callbacks to LinkedIn, X, TikTok, or YouTube by analogy.

## Additive migrations and data relationships

- `2026_11_14_172001_create_meta_data_deletion_requests.php`: independent global request identity, signature SHA-256 fingerprint, stable HMAC actor digest, encrypted short-lived actor identifier, unguessable confirmation, state, counters, error code, timestamps.
- Same migration `meta_data_deletion_targets`: row per affected organisation-owned provider connection, with composite tenant FK and independent state/review reason.
- `2026_11_14_172002_add_meta_actor_digest_to_social_oauth_selections.php`: indexed stable HMAC for active, encrypted Meta OAuth grants; older, at-most-10-minute selections are supported by an encrypted fallback scan.
- `MetaDataDeletionRequestFactory`, `MetaDataDeletionTargetFactory`: explicit, synthetic fixtures; no real provider tokens.
- Filled provider-derived snapshot, webhook, cursor, analytics, and API-log factory classes, with `forAccount()` tenant consistency.
- Optional local-only demonstration seeders do not create real privacy requests or change real provider grants.

## Callback and status contracts

`POST /api/meta/data-deletion` accepts form-encoded `signed_request` (anonymous, no CSRF cookie). Verifier checks base64url structure, raw-byte HMAC-SHA256 signature with `META_APP_SECRET`, supported algorithm, app-scoped user ID, issued-at freshness and any declared expiration. Never log the request body. Returns exactly JSON `{"url":"https://.../meta/data-deletion/status/{code}","confirmation_code":"{48-char-code}"}` with `Cache-Control: no-store`.

`GET /meta/data-deletion/status/{code}` is an unauthenticated, noindex, no-store, human-readable HTML page. Only confirmation and safe lifecycle state/timestamps are shown; it never displays a user ID, email, organisation, account name or token. Unrecognised codes return 404.

`GET /privacy/data-deletion` provides public instructions for initiating a request through Meta Apps & Websites and distinguishes local account disconnection from data deletion. **It is NOT a complete privacy policy; publish the full policy and a genuine privacy contact before Meta App Review.**

Configure **APP_URL=https://your-real-deployed-domain**, `META_APP_ID`, `META_APP_SECRET`, and **stable** `META_ACTOR_HMAC_KEY` (independently generated, securely stored). Do not expose secrets to React, Sentry breadcrumbs, logs or UTCP/MCP tools. Never rotate the actor HMAC key without controlled rekeying of existing digests. Set the Meta App Dashboard's **Data Deletion Request URL** to `https://your-domain/api/meta/data-deletion` and the privacy policy / instructions link appropriately. Register HTTPS/TLS before testing remotely.

## State and transactional guarantees

- **PENDING**: request durably accepted, credentials deleted immediately, provider connections REVOKED and account capabilities UNAVAILABLE.
- **PROCESSING**: one worker owns the DB transaction and processes linked provider-derived data.
- **COMPLETED**: mapped provider-derived data erased, no outstanding review reason; the encrypted actor identifier is nulled.
- **NEEDS_REVIEW**: business-authored content or unattributable Meta webhooks could contain user data, or the connection was reauthorized mid-flow. *Never mark complete automatically.*
- **FAILED**: recoverable error; scheduler retries up to three attempts, with only a safe code exposed operationally. Privacy status remains uncompleted.

The receiver matches `provider_connections.external_actor_id = signed_request.user_id` for **both** Meta providers across **every organisation**, locks those grants, erases `provider_connection_credentials`, revokes each grant, disables its accounts, and consumes matching in-flight encrypted account selections. Unrelated Meta actors and other providers are untouched.

The scheduled command `php artisan planner:process-meta-deletions --limit=20` runs each minute. It deletes provider-derived analytic snapshots/rollups, integration sync cursors, attributable webhook events, and attributable provider API logs. It clears status-event `details`, archives and pseudonymizes affected social-account records (needed as FK tombstones), and removes provider identity, scopes and secret refs from the grant. Records for organisation-authored content are retained **pending manual privacy review** rather than silently erased. Remote provider posts are not deleted by this application callback.

OAuth flows initiated before the deletion are denied by `blocksExistingOAuth` in both OAuth callback completion and account-selection confirmation; re-consent after completion is permitted through a fresh OAuth flow. The application must not bypass this in future publishing/UTCP/MCP services.

### Known high-priority gap / production gate

The `NEEDS_REVIEW` workflow does **not yet include a privileged manual resolution UI/command** with reviewer attribution, legal-hold evidence, verified erasure, and final disposition. Until that workflow is complete, retention-review requests can remain open indefinitely. Before production/app review: implement this operator workflow, audit related data stores for unattributable provider data (including S3/log archives and unlinked events), and publish a complete privacy policy. This increment is a solid callback/erasure foundation, **not complete privacy compliance**.

Provider-side revocation on brand disconnect remains deliberately disabled until shared grant reference/consent ownership is mapped and provider-specific revocation endpoints tested. This avoids revoking another brand's access. Add specific rules for Google OAuth (revocation can affect all scopes granted to the app) and all other providers independently.

## Architecturally enforced conventions

- Only resource CRUD methods or single-action `__invoke` controllers. Public signed callbacks are dedicated `__invoke` controllers, intentionally exempt from Kinship because the sender is Meta, not a Planner user.
- DB access inside `App/Repository/Contracts` and `App/Repository/Eloquent`, business use cases in `App/Services/Social`.
- Only Eloquent model factories for test fixtures; `DB` facade only for production database transactions, not raw fixture inserts.
- All user-authenticated account management routes still require Kinship at the edge and service-level brand scoping.
- No direct exposure of provider tokens, deleted Meta actor identifiers, or confirmation codes in general API/logs.

## Tests and verification

Authored tests:
- `tests/Unit/Social/MetaSignedRequestVerifierTest.php` — valid signature, tampering, forbidden algorithm, expiration, malformed requests.
- `tests/Feature/Social/MetaDataDeletionCallbackTest.php` — cross-organisation FB/IG matching, immediate token erasure, no impact on an unrelated Meta actor, signed-request idempotency, unknown actor, public confirmation and secrecy, expired OAuth selection invalidation, provider-derived row deletion, review-required for unlinked webhooks, stale OAuth fencing.

Runtime test sequence in disposable MySQL:

```bash
composer install
php artisan migrate --force
php artisan test --filter=MetaSignedRequestVerifierTest
php artisan test --filter=MetaDataDeletionCallbackTest
php artisan route:list --path=meta
php artisan planner:process-meta-deletions --limit=20
php artisan schedule:list
```

Then test with a Meta development app and real HTTPS registered callback; validate `signed_request`, confirmation/status URL, OAuth revocation, multi-brand identity match, replay and new-consent recovery. **None of these Laravel/MySQL or provider integration tests were executed in this workspace** (no `vendor` or `pdo_mysql`). Static PHP syntax, route/contract source checks and archive integrity are separate, not substitutes for runtime tests. The isolated dependency-free HMAC smoke confirmed valid signed payload, tamper rejection and expiry rejection (using stand-in Symfony exception classes, **not** a PHPUnit or framework integration run).

## Next tasks

1. Implement privileged manual review/final disposition, privacy contact and compliant full privacy policy.
2. Build separate provider deauthorization event adapters for Meta, Google/YouTube, LinkedIn, X and TikTok based on their documented contracts. Implement a reference-counted and consent-aware grant ownership model before making remote revoke calls.
3. Add Meta token introspection (debug_token) and per-account capability verification, with verified scopes and expiry, only after live permissions are available.
4. Implement operational alerts for privacy requests requiring review or failed after retries; avoid sensitive data in alerts.
5. Execute complete MySQL, Kinship, OAuth and browser E2E tests, then reconcile backlog tracker against observed results.
