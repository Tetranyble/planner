# Planner Engineering Master Plan — Start Here

**Status:** Planning baseline v1.0, 14 November 2026. **Repository snapshot:** `Planner_Laravel_Kinship_Storage_Fixes_2026-11-14.zip` as extracted in this deliverable. **Not a claim of runtime readiness.**

## Mission and boundaries

Build a secure, multi-organisation, multi-brand marketing operating system for Pensuh and future brands. Capabilities: brand profiles and targeting; connected social accounts; marketing campaigns; content, media and platform variations; approval and durable publishing; AI-assisted content planning; scheduled rules; lead consent and outreach; attribution and analytics; operational tooling; and a ChatGPT-facing MCP adapter plus UTCP direct-tool discovery. The platform's own Laravel services are the single source of business truth.

## Read these in order

1. `CONTINUE_HERE.md` at the repository root — compact state, exact next step, context for a fresh chat.
2. `docs/engineering/01_ARCHITECTURE_AND_BOUNDARIES.md` — modular architecture and package responsibilities.
3. `docs/engineering/02_REPOSITORY_PATTERN.md` — **mandatory** rules for `app/Repository`, repository contracts, tenancy and services.
4. `docs/engineering/03_QUALITY_AND_TESTING.md` — test pyramid, MySQL integration tests, quality gates and CI commands.
5. `docs/engineering/04_MASTER_IMPLEMENTATION_ROADMAP.md` — phases, work items, dependencies, tests and exit criteria.
6. `docs/engineering/05_SECURITY_AND_DATA_INTEGRITY.md` — tenant isolation, approvals, consent, security and schema hardening.
7. `docs/engineering/06_API_AI_AND_PROTOCOLS.md` — REST / UTCP / MCP contracts and delegated authorization.
8. `docs/engineering/07_DEPLOYMENT_AND_OPERATIONS.md` — environments, workers, releases, recovery and observability.
9. `docs/engineering/08_DECISIONS_RISKS_AND_OPEN_QUESTIONS.md` — decisions and unresolved assumptions.
10. `docs/engineering/09_HANDOFF_AND_PROGRESS.md` — end-of-session updates and prompts for future conversations.
11. `docs/engineering/PHASE_TRACKER.csv` — machine-readable workstream status, never silently mark work as done.
12. `docs/engineering/TEST_MATRIX.csv` — initial cross-layer test inventory; keep synchronized with actual test names.
13. `docs/engineering/10_PRODUCT_UI_AND_USER_JOURNEYS.md` — full screens and end-to-end user journeys.
14. `docs/engineering/11_DOMAIN_LIFECYCLES.md` — enum-aligned state machines and transitions.
15. `docs/engineering/12_FEATURE_TICKET_TEMPLATE.md` — acceptance/test evidence template for each implementation ticket.

## Snapshot-specific facts (verified by reading extracted source)

- Laravel `^13.17`, PHP `^8.3` in `composer.json`; uploaded project rules refer to PHP 8.4.
- MySQL schema expressed in `database/migrations`; PHP 8.4 CLI present in examination environment.
- `composer.lock` exists; check the resolved versions again locally with `composer show --direct` before using package APIs. Prior remediation notes state Kinship 2.0.1 and Storage 3.0.0.
- Application folders: `app/Models`, `app/Enums`, `app/Repository/{Contracts,Eloquent}`, `app/Services`, `app/Authorization`, `tests/{Unit,Feature}`.
- Repository abstractions already exist in `app/Repository/Contracts/Persistence.php`, `app/Repository/BaseRepository.php`, `app/Repository/Eloquent/UserRepository.php`.
- The current HTTP route set is minimal; no complete marketing API is implemented.
- Kinship and Storage integration changes are present as source patches, **not yet verified in a running MySQL/package integration environment**.
- The previous static-check report is `docs/IMPLEMENTATION_FIXES_2026-11-14.md`; never equate static checks with production acceptance.
- Package ownership: Kinship owns roles/permissions; Tetranyble Storage owns canonical media and physical storage. Do not duplicate them.

## Highest-priority next action

**Phase 0:** bring up an isolated MySQL test database, install locked Composer packages, run fresh/rollback migrations, verify Kinship and Storage host contracts, fix actual runtime errors with regression tests. Simultaneously correct and test the existing `UserRepository::save(array)` anomaly and define a hard tenant-scoped repository contract. Do not implement publishing or AI tool writes until these gates pass.

## Definition of ready for each feature

A scoped ticket has an owner, business use case, data model/migration impact, API contract, permission name, tenant/brand scope, expected state transitions, negative/failure scenarios, observable telemetry, security implications, unit/feature/integration tests, and rollout/rollback instructions.

## Definition of done for each feature

Tests written and passing (unit + feature + MySQL integration where persistence matters); coding follows the existing repository layer; no cross-tenant leaks; authorization and idempotency tested; failure and concurrency paths handled; queue jobs recover; documentation and tracker updated; PR reviewed; lint/static analysis and migrations green; staging validated with evidence; safe deploy/rollback path recorded. Skipped/flaky tests or missing vendor integration checks block completion.

## Important execution truth

This documentation is a plan and handoff artifact. It does not magically implement the roadmap, deploy tools, connect ChatGPT, or prove external social API approval. Whenever a task is completed, record exact commit/hash, test output, migration result and open risks in the tracker and handoff.
