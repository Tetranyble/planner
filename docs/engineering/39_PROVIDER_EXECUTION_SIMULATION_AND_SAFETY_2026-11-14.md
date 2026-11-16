# Increment 39 — Provider execution contracts and offline failure simulator

**Source baseline:** `Planner_Batch1_Reconciliation_Offline_Delivery_2026-11-14.zip` (increment 38). This is a significant self-contained **execution safety and deterministic simulation batch**, not live publishing.

## Shipped

- Provider-neutral `ProviderDispatchContract`, immutable `DispatchEnvelope` and typed `DispatchOutcome` with explicit provider receipts for success and redacted operator-safe summaries.
- `ProviderFailureClassifier` distinguishes rejected requests, 429 rate limits, safe pre-send 5xx failures and ambiguous timeouts, 2xx lacking receipts and 5xx responses after a potentially dispatched request.
- `RetrySafetyPolicy` quarantines ambiguous results, blocks replay without verified provider idempotency and attempts budget, honors bounded `Retry-After` and exponential backoff, and fails final errors.
- `DispatchSafetyGate` checks twelve independent attested prerequisites and **always blocks remote dispatch** while no production provider adapters have been integrated and verified.
- Hermetic `SimulationAdapter` and `SimulationHarness`: 6 providers × 7 response scenarios = 42 offline scenarios, no storage/database/network calls, no provider secrets, no real post receipts. Returned receipts are expressly synthetic and the redacted output never includes them.
- Permission-gated `POST .../content/publication-delivery/simulate` (organisation context, Kinship, BrandPermissionGuard, rate limit); `planner:simulate-publication` CLI; React simulation panel inside the existing Publication planning desk.
- Unit PHPUnit source, zero-dependency smoke script, frontend regression tests, engineering handoff.

## Verification executed in this environment

- `php scripts/check_publication_simulation.php` — 42/42 scenario assertions passed.
- `node --test tests/Frontend/publication-execution-simulation.test.mjs` — 3/3 passed.
- `node --test tests/Frontend/*.test.mjs` — 85/88 passed; **three preexisting tests cannot import `@babel/parser` without installed node_modules**. The other 85 passed.
- `php -l` applied to PHP sources under app/routes/tests/database/scripts — no syntax errors.
- `php scripts/check_repository_boundaries.php` — 30 repositories passed.
- **Not executed:** PHPUnit tests, MySQL migrations, Laravel route boot/authorization, Vite production build, browser E2E or real provider calls. Treat all of these as release gates.

## Runtime commands and precautions

```bash
php scripts/check_publication_simulation.php
php artisan planner:simulate-publication facebook timeout
php artisan planner:simulate-publication youtube rate_limit --attempts=2 --maximum=4
php artisan test --filter=PublicationExecutionSimulationTest
npm ci && npm run build
```

The simulator API accepts only enumerated providers and scenarios. It does not accept tokens, raw provider response bodies, arbitrary endpoints, or account data. Its idempotency option affects **offline advice only**, not dispatcher permissions.

## Hard boundaries

- **No real HTTP adapters, no credential access, no publication dispatch, no schedules promoted to SCHEDULED, no job state transitions or retries from this batch.**
- A successful simulation is NOT proof that a provider created a post.
- Human reconciliation dispositions are not platform acknowledgments.
- Rate limits and 5xx semantics differ by provider; the classifier is only a conservative baseline and must be refined and validated against each API's documented behavior before production.
- The remote kill switch remains effectively permanently ON because `DispatchSafetyGate` hard-blocks all dispatch.
- Next step: provider-by-provider sandbox transport implementations with capability attestations, request/response contract tests, stable identity and idempotency rules, controlled persisted outbox processor and dispatch-time approval/media revalidation. Do not enable live posting until true MySQL/Kinship/Storage and provider integration tests pass.
- Pensuh.com compliance/legal public pages are still **NOT DEPLOYED**.
