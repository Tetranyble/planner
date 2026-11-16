# Environments, CI/CD, operations and recovery

## Environment matrix

| Environment | Purpose | Database | Provider calls | Data safeguards |
|---|---|---|---|---|
| Developer local | focused unit and feature work | local MySQL 8.x test DB | fakes by default | synthetic fixtures |
| CI | reproducible gates | ephemeral/disposable MySQL service | HTTP fakes; opt-in sandbox | no live organisation data |
| Staging | E2E + integration | dedicated isolated MySQL | provider sandbox/limited test accounts | private credentials, scheduled test posts only |
| Production | real brand campaigns | managed MySQL with backups | production scoped OAuth | approval, audit, monitoring, rollback |

## Build and quality pipeline

1. Verify clean source, Composer lock hash, required PHP extensions, provider package versions.
2. Dependency audit and secrets scan; `composer validate --strict`.
3. Install locked Composer dependencies (only where available).
4. Pint style, PHP lint and, once added, static analyzer at a gradually stricter baseline.
5. Unit test suite; MySQL-backed feature + repository integration tests; package integration tests; protocol contract tests.
6. Migration-up/down on disposable MySQL; check script is supplemental; factories/data integrity tests.
7. E2E staging smoke and at least one durable-worker recovery scenario; verify API/OpenAPI/UTCP/MCP schema output.
8. Build immutable deploy artifact, release notes, DB migration assessment, rollout/rollback instructions.
9. Change approval; deploy with feature flags and canary/small-brand rollout; monitor for a defined observation window.
10. Capture evidence links and update handoff.

**Installable CI example for later configuration:** GitHub Actions PHP 8.4, MySQL 8.x service, Composer cache, one job for unit, one for MySQL integration, one for static security. Do not commit a workflow that falsely reports success because tests were skipped.

## Safe release sequence

- Back up DB and secrets references, verify restore drill and target schema version.
- Block destructive migrations when old code may still be running. Prefer expand/backfill/validate/contract migrations across multiple deployments.
- Deploy migration and application in compatible order, restart queue workers with graceful shutdown, verify outbox/lease recovery.
- In production, `migrate --force` is acceptable only after explicit target and backup verification; **never use `migrate:fresh` against persistent data**.
- Run smoke tests against a test organisation/brand; observe provider rates and queue backlog; roll back code where schema compatible; roll forward data migrations as needed.

## Workers and scheduler

- Distinct queue/worker pools for outbox, publisher, media processing, AI runs, analytics, outreach and notifications if load warrants; all use bounded timeouts/retries.
- Every durable work item: persisted intent, unique idempotency key, state machine, lease owner/deadline, retry/backoff, last error, correlation/trace ID, terminal resolution path.
- Outbox processor claims rows with DB locks, dispatches after committed state, marks success only after durable handoff.
- Publisher must reconcile `UNCERTAIN` external outcomes, not just replay all failed HTTP requests.
- Use scheduled jobs to discover due DB rows; database remains authoritative when Redis loses cache/queue messages.
- Prevent overlapping multi-runner scheduler discovery via DB locking / scoped mutex; test two workers.

## Runbooks to create during implementation

| Incident | First response | Recovery path |
|---|---|---|
| Provider token revoked | disable publish capability, alert owner, no infinite refresh loop | explicit reconnection + controlled retry |
| Timeout after public post request | mark uncertain, search provider by supported correlation | reconcile/manual investigation, then finish safely |
| Job stuck leased | detect expired lease, check provider operation state | release/reconcile, bounded retry |
| Queue backlog grows | inspect worker error rate, DB locks, quotas | scale consumers without duplicating external requests |
| Wrong-brand access found | disable affected routes/tools, preserve logs | revoke tokens, identify exposure, patch + negative tests |
| Storage object missing | prevent publish, record asset invalid, notify | repair package-owned object/variant, then retry |
| Consent withdrawn before send | stop sequence and suppress queued dispatch | audit and refuse any further send |
| New provider schema fails | pause adapter integration, quarantine payload | update fixture/mapping/capability checks |
| Suspected credential leak | revoke/rotate provider tokens, audit affected operations | postmortem and secret scanner/test hardening |

## Required observability signals

Latency/5xx by service and tool; queue backlog/due lag; lease expiry; duplicate-idempotency hits; provider rate limit/refresh/revocation; unknown publication count and age; approval pending age; AI tool failures and budget; Storage errors; invalid consent dispatch attempted; webhook signature failures; outbox oldest unprocessed event.

## SLOs — placeholders, must be set from baseline

- Provider publication success rate and SLA depend on provider processing rules; do not promise guaranteed publish-at-time precision before measurement.
- Define recovery time, restore point, tool latency and queue lag targets during production readiness (Phase 14–15) with measured evidence.
- Alert on **any** detected cross-tenant access and any unreconciled externally visible duplicate.
