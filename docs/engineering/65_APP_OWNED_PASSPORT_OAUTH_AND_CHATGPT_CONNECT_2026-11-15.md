# Increment 65 — Planner-owned Passport OAuth for ChatGPT MCP

Date: 2026-11-15 · **Source implemented, runtime-unverified** · Based on Increment 64.

## Route ownership requirement (mandatory)

Passport 13 is declared in `composer.json`, **without changing `composer.lock`**. `AppServiceProvider::register` calls `Passport::ignoreRoutes()`. This disables all of Passport's implicit package routes. Planner declares its own OAuth routes in `routes/planner_oauth.php`, included from `routes/ai.php`. **Do not call `Mcp::oauthRoutes()` or publish another hidden route registrar.** Our single-action, application-owned controllers implement discovery/registration/consent checks; the maintained Passport authorization-code/token controllers are invoked only as cryptographic engines, through thin Planner controllers, never exposed as implicit HTTP routes.

Explicit routes:

| Method and path | Purpose | Authorization |
| --- | --- | --- |
| `GET /.well-known/oauth-authorization-server` and nested variant | Authorization-server metadata (issuer, PKCE S256, DCR) | Public; disabled if OAuth off |
| `GET /.well-known/oauth-protected-resource` and `/mcp/planner-agent-oauth` variant | Protected resource metadata | Public; disabled if OAuth off |
| `POST /oauth/register` | Strict HTTPS-host-allowlisted dynamic public-client registration | OAuth + separate DCR release switches, rate limit |
| `GET /oauth/authorize` | Validate authorization-code request with PKCE S256 and mcp:use scope | Browser session + login when necessary |
| `POST /oauth/authorize` | Human chooses an existing one-brand delegation and approves | CSRF, verified signed-in user, matching OAuth session |
| `DELETE /oauth/authorize` | Deny authorization | CSRF, user session |
| `POST /oauth/token` | Authorization code/refresh-token exchange | Rate-limited; Passport handles signed tokens |
| `GET /oauth/links` / `DELETE /oauth/links/{linkId}` | Inspect and disconnect linked clients | Verified signed-in owner of link; CSRF for delete |
| `POST /mcp/planner-agent-oauth` | Authenticated governed agent tools | Passport API guard + current grant, brand/Kinship, rate limits |

The existing Sanctum `/mcp/planner` (read-only) and `/mcp/planner-agent` (scoped bearer) remain unchanged. There is **no anonymous research/posting endpoint**.

## OAuth security and account isolation

- `PlannerOAuthUser extends User implements OAuthenticatable` is the **separate Passport user provider** for `auth:api`; do not install Passport's `HasApiTokens` trait on the normal User: it would replace Sanctum token handling. For Kinship membership checks, the middleware reloads the canonical User subject.
- DCR is disabled by default, 6 requests/minute, accepts only public code+PKCE clients, exact configured HTTPS callback hostnames (`PLANNER_OAUTH_ALLOWED_REDIRECT_HOSTS`), no user info, ports, fragments, or arbitrary callback domains. Match the actual callback hostname shown by ChatGPT and update the allowlist explicitly; no wildcard allowed.
- Explicit consent binds one OAuth `client_id` and one human `user_id` to a **previously issued active agent delegation** (one organisation, brand, review mode, expiry, daily limits, optional owner-approved posting account allowlist). No agent can grant or approve itself. Switching a client to another delegation requires registering a new client.
- A revoked link invalidates the current Passport access tokens and associated refresh tokens; re-consenting to the same delegation may create new tokens but never resurrects revoked tokens. Removing Kinship membership, delegation revocation, owner demotion or expiry also denies access on every request.
- MCP OAuth bearer must have exactly `mcp:use` and a valid Passport signature, token expiry, current user/client link and current brand membership. The `request()->user()` resolver becomes the canonical User after OAuth validation, so tools reuse the existing `OrchestratePlannerAgent` checks, audit/idempotency, no self-approval, account allowlists, global emergency stop and provider release controls.
- No raw OAuth tokens, session requests, provider credentials or agent grant secrets enter LLM-visible tool responses. Link listing shows only client/brand/delegation IDs.

## Local installation/acceptance — requires your environment

1. Back up and compare `composer.json`, `composer.lock`, and `package-lock.json`. **Do not remove lockfiles**. On your machine with PHP 8.4 and required extensions run `composer update laravel/passport laravel/mcp --with-all-dependencies`, then `composer install`. Inspect the solver and updated lock diff. In this delivery, PHP dependencies were not installed.
2. Review Passport 13 migrations and publish as appropriate: `php artisan vendor:publish --tag=passport-migrations`. Compare with existing schema; use only additive migrations on an existing database. Generate signing keys with `php artisan passport:keys` and restrict key permissions. Do not use `php artisan install:api --passport` blindly; it may change existing Sanctum guard/route scaffolding.
3. Configure real HTTPS `APP_URL` and safe defaults initially: `PLANNER_OAUTH_ENABLED=false`, `PLANNER_OAUTH_DCR_ENABLED=false`, `PLANNER_OAUTH_ALLOWED_REDIRECT_HOSTS=chatgpt.com`. Run `php artisan route:list --path=oauth` and check every app-owned route exactly once, with **no vendor auto-route duplicates**. Verify issuer metadata and protected resource discovery against the deployed URL.
4. On disposable MySQL: `php artisan migrate` (review migration list first), `php artisan test --filter=PlannerOAuth`, `php artisan test --testsuite=Integration`, `node --test tests/Frontend/planner-oauth-owned-routes.test.mjs`, `npm ci && npm run build`.
5. Create a brand with an active owner and issue a short-lived **Review Required** delegation in Planner before connecting. For sandbox testing only, enable `PLANNER_AGENT_ACCESS_ENABLED=true`, `PLANNER_AGENT_WRITE_ENABLED=true`, `PLANNER_OAUTH_ENABLED=true`, `PLANNER_OAUTH_DCR_ENABLED=true`. Keep `PLANNER_AGENT_DELEGATED_PUBLISHING_ENABLED=false` and `PLANNER_EMERGENCY_PUBLISH_STOP=true` until approved-provider E2E verification.
6. Configure a custom MCP server pointing to `https://YOUR_PLANNER_HOST/mcp/planner-agent-oauth` in ChatGPT Plugins. Use OAuth with DCR and check the actual callback URL hostname before allowlisting it. Authenticate interactively, choose brand/delegation, and approve. The OAuth code exchange requires S256 PKCE. Confirm tools can create work orders/drafts and request **human** review, but cannot self-approve or publish without a distinct explicit delegation.
7. Test revoked OAuth link, revoked delegation, expired token, changed brand/organisation membership, org-owner demotion, wrong client ID, wrong code verifier, cross-tenant access, multiple browser tabs, disallowed Origin, expired refresh token, bad redirect host, and rate-limit burst. Confirm revoked refresh tokens never issue new access.

## Remaining runtime blockers (not passed)

Composer dependencies are absent in this environment; Laravel route boot, Passport migrations, signature/PKCE exchange, new MySQL integration tests, secure browser login, ChatGPT DCR handshake and external MCP connection are **NOT tested**. Static PHP lint and Node source guards are not runtime proof. Live social media posting and Pensuh.com privacy/legal public-page deployment remain separate, disabled or open workstreams.
