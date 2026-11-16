# Increment 35 — Read-only publication readiness diagnostics

**Baseline**: Full Increment 34 archive. This is not a scheduler, publisher, or dispatch authorization.

## Scope

- New `GET /api/v1/organisations/{organisation:uuid}/brands/{brand:uuid}/content/{itemUuid}/targets/{targetUuid}/approvals/{requestId}/readiness` with `auth:sanctum`, verified user, organisation context, Kinship `approval_request.index`, and independent brand guard.
- The request and target must belong to the same brand-scoped content item; mismatches return 404. No cross-tenant lookup falls through.
- Read-only `AssessPublicationReadiness` checks draft/latest-version state, approved review, expiry, current active policy, exact recomputed approval snapshot/quorum, canonical Tetranyble Storage hashes, account/provider connection status, credential presence/15-minute token lifetime, and fresh *AUTOMATIC* `publish` capability.
- New `PublicationReadinessRepositoryContract` and Eloquent implementation avoid exposing encrypted tokens and scope all lookups to organisation/brand; all blockers are safe symbolic identifiers.
- Reuses `ManageContentApprovals::currentSnapshot` for precise policy/rule hashing, including nullable risk declarations; avoids a second policy rule algorithm.
- Always returns `readiness=BLOCKED`, `can_schedule=false`, `can_publish=false`, and `PUBLISHING_ENGINE_NOT_IMPLEMENTED`; existing targets are ASSISTED-only. No posting, no scheduling, no outbox job, no remote OAuth call, no new schema.

## Important caveats

- `publish` is a deliberately strict placeholder capability name. Do not change to loosely match generic platform capabilities: establish provider-specific entitlement mappings and live attestations before authorizing a future dispatcher.
- This endpoint is **diagnostic only**. A future dispatch transaction MUST re-evaluate all invariants under concurrency and a durable idempotency policy. The preview is not a durable certificate.
- Credential validity is a conservative precheck and is not a provider authorization claim; refresh, revocation, data-access limits, scope changes, media type limits and provider app-review approval still require provider-specific checks.
- Approved request expiry is treated conservatively as a blocker. Existing worker expires only pending requests; choose an explicit validity policy before scheduling.
- Pensuh.com legal/privacy pages remain open and NOT deployed.

## Evidence

- `node --test tests/Frontend/publication-readiness-architecture.test.mjs`: 3/3 PASS.
- PHP lint on new service and repository: PASS.
- Dependency-free PHP pure-rules smoke: PASS (11 blocking reasons for entirely invalid input).
- PHP Unit test source: `tests/Unit/Content/PublicationReadinessRulesTest.php`; HTTP Feature test source appended to `tests/Feature/Content/ContentApprovalWorkflowTest.php`.
- Laravel PHPUnit/MySQL, end-to-end Kinship/Storage, browser/Vite and live provider tests: **NOT RUN** here. Must run after `composer install`, `npm ci`, and a disposable MySQL setup.

## Next slice

1. Validate the real Laravel/Kinship/Storage runtime; run new Feature tests and resolve any failures.
2. Define provider-specific publish-entitlement matrix, credential scope/expiry, media capabilities, explicit human review validity period, and role-based review UI.
3. Only then implement idempotent durable schedule intents under owner confirmation; no remote provider I/O while holding DB locks. Build a separate dispatch engine after verified live provider capability checks.
