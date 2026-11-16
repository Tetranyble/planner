# Increment 41 — Offline provider HTTP-shaped contracts

Baseline: `Planner_Batch1_Durable_Sandbox_Execution_2026-11-14.zip` (Increment 40).

## Delivered

- Strict in-process `MockHttpTransportContract`, immutable synthetic request/reply values, and a fixture transport with **no HTTP client, URL destination, sockets, secrets, or real provider API routes**.
- `MockProviderRequestCompiler` for Facebook, Instagram, LinkedIn, X, TikTok, and YouTube. It checks conservative mock media categories and builds synthetic paths only (`/mock/v1/{provider}/publish`). These are **internal rehearsal schemas, NOT real provider API request definitions**.
- `MockProviderDispatchAdapter` classifies acknowledged synthetic receipts, rate limiting, authorization rejection, transient/pre-send faults, ambiguous server responses and missing receipts through the existing classifier/retry policy.
- The original `SimulationHarness` now supports `mock_confirmed`, `mock_accepted_no_receipt`, `mock_rate_limited`, `mock_rejected`, `mock_server_error`, `mock_timeout`, `mock_failed_before_send`. Because `RunPublicationSandbox` already uses `SimulationHarness`, these fixtures can be queued with `planner:queue-publication-sandbox` and processed through the existing durable, lease-fenced ledger. No data migration was required.
- Unit test source, a zero-dependency 42-fixture/160-assertion command, frontend architecture guards, and updated continuity tracker.

## Developer checks

```bash
php scripts/check_mock_provider_transport.php
php scripts/check_publication_simulation.php
php scripts/check_publication_sandbox.php
node --test tests/Frontend/mock-provider-transport-architecture.test.mjs
php artisan planner:simulate-publication facebook mock_confirmed
# Requires configured disposable MySQL and a legitimate held publication ID:
php artisan planner:queue-publication-sandbox <org> <brand> <held-job> facebook mock_timeout test:mock:001
php artisan planner:process-publication-sandbox --limit=20
php artisan test --filter=OfflineMockProviderTransportTest
```

Only synthetic runs return synthetic receipts (`MOCK_*`). They are never evidence of an actual social post, cannot change the real publication state, and never contact provider APIs. No live publishing adapter was enabled.

## Security and pending gates

- Actual network transport, credential injection, platform-specific media/container workflows, receipt verification and approved app permissions **are not implemented**. The mock interfaces must NEVER be auto-bound to a live delivery worker.
- Real MySQL/PHUnit integration, Kinship, Storage, Vite/browser and provider sandbox verifications remain pending. The safety gate continues to reject every live dispatch even with otherwise-true flags.
- Synthetic media constraints are conservative and not a statement of current real network API support. Before live integrations, verify each provider's official docs and granted scopes.
- Pensuh.com privacy, legal and data deletion pages remain documented, not deployed.
