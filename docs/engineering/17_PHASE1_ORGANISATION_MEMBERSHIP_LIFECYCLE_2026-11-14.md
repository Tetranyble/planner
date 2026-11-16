# Planner Phase 1 — Organisation Membership Lifecycle

**Prepared:** 2026-11-14  **Baseline:** `Planner_Phase1_Onboarding_Brand_Creation_2026-11-14.zip`  **Implementation state:** implemented, runtime-unverified

This is a self-contained engineering handoff for the next session. It follows `App/Repository/Contracts` + `App/Repository/Eloquent`, reuses Kinship for workspace roles, preserves React/Inertia/shadcn and the theme setup, and does not replace Tetranyble Storage.

## Scope implemented

- New `organisation_invitations` migration with UUID, role, email, SHA-256 bearer-token digest, 7-day expiry, acceptance/revocation timestamps, a generated unique pending marker and foreign keys. It is an **additive migration**. Do not re-run destructive migrations against real data.
- `OrganisationInvitation` model uses existing `Uuidable`, never serializes `token_hash`, and casts timestamps.
- Contracts/Eloquent: `OrganisationMemberRepository`, `OrganisationInvitationRepository`, `OrganisationInvitationOutboxRepository`; all registered in `AppServiceProvider`.
- Service `ManageOrganisationMembers`: list/paginate organisation members; invite by normalized email; cancel; accept (verified email + matching token); promote/demote roles; revoke; audit mutations. Verifies selected active organisation + Kinship `membership.manage` before management operations; invitation acceptance is a separate token-authenticated path.
- Hardened existing `OrganisationMembershipService`: owner-role transitions are double-checked inside a DB transaction protected by an organisation row lock. Last active owner cannot be demoted/revoked. Revocation removes Kinship assignments *and* marks all brand memberships revoked. Existing `joined_at` is preserved during a role edit. Only the first verified creator can bootstrap the initial owner grant.
- Frontend: `Organisations/Members.jsx`, `Organisations/AcceptInvitation.jsx`, accessible invitation/review forms and status messages. Authenticated layout now shows flash messages. Existing brand list has a Members link only for authorized users.
- REST + web routes share services, not separate business logic.
- Outbox worker `planner:send-organisation-invitations`: encrypted token delivery instruction stored in existing transactional `outbox_events` record. Scheduler claims pending or expired-leased events with `FOR UPDATE SKIP LOCKED`, sends mail, marks dispatched, retries failures with bounded exponential delay, and marks terminal failures. Invitations don't leak bearer tokens in API responses.

## New routes

### REST (under `auth:sanctum`)

| Method | URI | Purpose |
|---|---|---|
| GET | `/api/v1/organisations/{uuid}/members` | Membership directory, roles, pending invitation statuses; requires org context + `membership.manage` |
| POST | `/api/v1/organisations/{uuid}/members/invitations` | Invite by email and role; requires org context + permission and rate limiting |
| PATCH | `/api/v1/organisations/{uuid}/members/{userId}/role` | Change role; owner role guarded |
| DELETE | `/api/v1/organisations/{uuid}/members/{userId}` | Revoke membership + brand access |
| DELETE | `/api/v1/organisations/{uuid}/members/invitations/{uuid}` | Cancel pending invitation |
| POST | `/api/v1/invitations/accept` | Accept using `{token}` as authenticated, verified recipient |

### Web / Inertia

- `GET /organisations/{uuid}/members`
- `POST /organisations/{uuid}/members/invitations`
- `PATCH /organisations/{uuid}/members/{userId}/role`
- `DELETE /organisations/{uuid}/members/{userId}`
- `DELETE /organisations/{uuid}/members/invitations/{uuid}`
- `GET /invitations/{token}` — requires login+verified email and recipient match; sends `Referrer-Policy: no-referrer`
- `POST /invitations/{token}/accept` — CSRF protected and throttled

The accepted user's browser goes to their brand directory. Access to brands must still be separately granted; organisation membership does not grant automatic brand membership.

## Important security invariants

1. User ID, role name or UUID input from clients is never sufficient for authorization. Management operations require active selected organisation context and Kinship permission. Role resolution is workspace-scoped.
2. Only an existing **owner** can assign, demote, or revoke an owner. Row locks serialize changes. At least one active owner is required at all times.
3. Invitee must log in with, and verify, the address invited. Every token is 32 random bytes (hex-encoded), only SHA-256 stored in the invitation row. Encrypted raw token is held in the outbox until processed; never return it through the API, listing, audit detail or logs.
4. Duplicate active invitations to the same normalized address are prevented with a generated-column unique index. Re-inviting invalidates the previous token. Expired or cancelled invitations cannot be accepted.
5. An organisation revocation revokes all existing brand memberships and Kinship role grants. Rejoining the organisation does not silently restore brand access.
6. Scopes for brand content remain independent. This increment does **not** implement per-brand invitations, member role assignment at the brand level, social providers or ChatGPT MCP tools.
7. Outbound invitation email is **at-least-once**: the SMTP provider cannot guarantee exactly-once delivery if an acknowledgement is lost. Repeated messages use the same invitation and only one token can be accepted. A leased event may be retried after crash; avoid raw token or email in logs.

## Operations / deployment

1. Fresh development environment: `composer install`, `npm ci`, configure `.env` with MySQL8, `APP_KEY`, authenticated mail transport, `APP_URL` (must be publicly accessible in invite email), queue/cache as desired. No secrets committed.
2. Test with `php artisan migrate:fresh --env=testing --force` **on disposable DB only**. Verify Kinship's package migrations load before own membership tables; verify MySQL generated columns and rollback, including `organisation_invitations`.
3. On an existing deployment, use **only** `php artisan migrate --force`, after backup and review of earlier schema changes. This increment adds one table and modifies PHP/domain code; no destructive schema modifications.
4. Run `php artisan schedule:work` or the platform scheduler (cron `* * * * * php /path/to/artisan schedule:run`) to trigger outbox email dispatch. For manual local delivery: `php artisan planner:send-organisation-invitations --limit=20`.
5. Monitoring: `SELECT state, COUNT(*) FROM outbox_events WHERE event_type='organisation.invitation.created' GROUP BY state;` Alert on `FAILED` and stale `LEASED` rows. Mail config errors do not automatically fail invite creation; the UI exposes current email state.
6. Verify all authorization + Kinship transitions in **real MySQL integration** before production. `php artisan test --testsuite=Unit`, then Feature, then Integration; run `php scripts/check_repository_boundaries.php`, `php scripts/verify_brand_scope.php`, `npm run test:membership-ui`, `npm run build`.

## Test inventory and actual validation

**Authored tests, NOT executed under PHPUnit in this runtime**:

- Unit: `tests/Unit/Membership/InvitationTokenTest.php`, `OwnerQuorumTest.php` (4 cases).
- Feature: `tests/Feature/Membership/OrganisationMembershipEndpointsTest.php` (6 cases: unauthorized, email-bound acceptance, expired/cancelled, last owner, role change/revocation, outbox mail).
- Integration: `tests/Integration/Membership/InvitationIntegrityTest.php` (4 cases: transaction/input refusal, duplicate pending constraint, cross-workspace role tampering, cross-organisation isolation).
- Frontend: `tests/Frontend/membership-pages.test.mjs` (3 cases).

**Executed in this environment**:

- PHP `php -l` of application/test/route/migration PHP files: passed.
- `php scripts/check_repository_boundaries.php`: passed (10 Eloquent repositories).
- `php scripts/verify_brand_scope.php`: passed (10 assertions).
- `npm run test:membership-ui`: passed 3/3, `npm run test:onboarding-ui`: passed 3/3, `npm run test:theme`: passed 10/10.
- Dependency-free InvitationToken smoke: passed.

**Blocked / pending**: Full PHPUnit feature and real MySQL integration: this container lacks PHP extensions (`mbstring`, `dom`, `xmlwriter`, `pdo_mysql`) and MySQL. `npm run build` / `node vite/bin/vite.js build` blocked on missing native Linux Rolldown binding from preexisting dependency bundle. Frontend tests ran with local reusable parser dependency, which is NOT included in output ZIP. The CI workflow reinstalls all dependencies and runs all suites; CI itself has not run here.

## Next engineering slice

Run Phase 0/1 CI against disposable real MySQL first; resolve any package/runtime differences before marking this increment verified. Then implement **brand membership administration** (brand member list, add/remove, role controls) using Kinship organisation permissions + separate brand membership checks. After that, continue `P02-01` brand profiles/targeting. Reconcile exact Pensuh source tokens if available. Update this document, `CONTINUE_HERE.md`, `PHASE_TRACKER.csv`, `TEST_MATRIX.csv` after each slice.
