# Increment 40 — Durable, offline publication execution queue

Baseline: `Planner_Batch1_Execution_Simulator_Safety_2026-11-14.zip` (Increment 39).

## Delivered

- Two additive MySQL tables: `publication_sandbox_runs`, `publication_sandbox_events`, with tenant- and brand-bound composite parent FK, unique operation keys, append-only application events, and queue/lease indexes.
- Repository contract + Eloquent implementation and service ownership. No raw SQL access to sandbox tables in the service.
- Idempotent enqueue against **held** (`AWAITING_USER`) publication jobs, requiring explicit org/brand/job identifiers and a bounded operation key. Duplicate enqueue with conflicting provider/scenario fails closed.
- Atomic MySQL `FOR UPDATE SKIP LOCKED` worker claim, short lease, fenced completion by random token, no network or credentials. Expired leased simulations are **quarantined**, not replayed.
- Offline `SimulationHarness` outcomes stored in a redacted JSON result. A synthetic receipt is not a real receipt. Real `publication_jobs` states, counters and metadata are never updated.
- Scheduler command (minute interval, single server lock), privileged local CLI enqueue/inspect, factory, syntax/static guards and integration test source.

## Operator commands (on configured, disposable MySQL Laravel environment)

```bash
php artisan migrate --force
php artisan planner:queue-publication-sandbox 1 1 123 facebook timeout test:simulation:001
php artisan planner:process-publication-sandbox --limit=20
php artisan planner:inspect-publication-sandbox 1 1 123
php scripts/check_publication_sandbox.php
php artisan test --filter=PublicationSandboxPersistenceTest
```

The ID values above are examples, not real verified records. A valid held publication must already exist. Do not use `migrate:fresh` against existing data.

## Safety, limitations and release gates

**This is not live provider delivery.** It does not implement an authenticated HTTP adapter, provider media upload, live posting worker, user-facing queue API, or retries of actual publications. No Laravel/MySQL tests have been executed in this environment. Live dispatch remains blocked by `DispatchSafetyGate`. The sandbox queue processes synthetic inputs only; even `SUCCESS` stays a sandbox result.

Worker safety is intentionally conservative: if a process crashes after claiming, expired lease is quarantined for operator inspection; no blind retries. One more test suite must cover racing claims, idempotency conflicts and concurrent workers on a real disposable MySQL database. The production launch also requires Kinship/package integration, Storage, provider API terms/capabilities and real sandbox provider fixtures.

Pensuh.com legal, privacy and deletion pages remain documented and **not deployed**.
