# Architecture decisions, risks, unknowns

## Accepted architecture decisions (as of 2026-11-14)

| ID | Decision | Why / limitation |
|---|---|---|
| ADR-001 | Laravel modular monolith + durable workers | lower complexity than microservices; deploy independently scalable workers if needed |
| ADR-002 | MySQL 8.x as transactional DB for current project | current project uses MySQL; validate MySQL-specific generated columns and foreign keys |
| ADR-003 | Kinship owns roles and org-scoped permission grants | avoid duplicate authorization system; needs brand-specific gates |
| ADR-004 | Tetranyble Storage owns physical media and quotas | no competing file registry; marketing tracks asset associations |
| ADR-005 | `app/Repository` contracts + Eloquent implementations are mandatory | honour existing code conventions, enable test seams; tenant-safe APIs required |
| ADR-006 | Approval binds exact immutable target version/hash | content change must invalidate approval |
| ADR-007 | Transactional outbox + DB lease/idempotency | durable jobs survive crash; provider outcomes can still be uncertain |
| ADR-008 | UTCP-first direct API tools | avoid proxy duplication for UTCP-native agents |
| ADR-009 | MCP adapter for ChatGPT | protocol compatibility, same business services, eligibility may vary by account |
| ADR-010 | Pensuh is first production brand | prove workflows before onboarding unrelated brands/customers |
| ADR-011 | Typed relational business rules; JSON only with documented reason | avoid hidden policy/audience/approval semantics |
| ADR-012 | PHPUnit test pyramid plus real-MySQL integrations | tests must exercise current runtime, not just mocked SQL |

## Resolved by source inspection, but not runtime-verified

- `app/Repository` has a generic Persistence contract, Eloquent base and UserRepository; needs tenancy-safe expansion.
- Kinship and Storage integration code/config exists. Exact runtime compatibility remains unverified without installed vendor packages and DB.
- Existing HTTP endpoints are only initial boilerplate; functional APIs are future work.
- Test folder currently has Unit and Feature suites; Integration suite not yet configured.
- Provider credentials and live posting validation were not available.

## Risks and proposed mitigations

| Risk | Severity | Required proof/mitigation |
|---|---|---|
| Unscoped `BaseRepository` queries expose another tenant's records | Critical | restricted tenant repo contract + cross-brand integration tests |
| Kinship role workspace resolver caching crosses org boundaries | Critical | actual v2 package tests across switching/revocation |
| Storage host workspace accessor doesn't match package assumptions | Critical | real package-integrated media tests before exposing routes |
| Duplicate external posts after network timeout | Critical | provider reconciliation and `UNCERTAIN` state tests |
| AI/MCP tool bypasses approval or impersonates actor | Critical | delegated scopes + approval hash and parity tests |
| Current `UserRepository::save` misinterprets Eloquent `save` argument | High | contract correction + tests before service usage |
| MySQL test suite isn't wired to disposable DB by default | High | CI MySQL service and `phpunit.xml`/env baseline |
| Platform APIs change or deny approval (notably direct TikTok posting) | High | capability registry, assisted workflow and official reviews |
| Outreach violates consent/suppression rules | High | source/consent audit and dispatch-time suppression check |
| Over-normalized migration uses inconsistent collation/case | Medium | migration/factory/unique index tests |
| Provider raw JSON lacks versioning/redaction/retention | Medium | schema version, validation, privacy retention |
| Multiple brands share media/token identity accidentally | High | explicit owner + brand association and negative tests |

## Open implementation choices (do not silently assume)

1. Which login/token approach and identity provider are used for browser and backend service-to-service API clients? Decide and record before Phase 1.
2. What exact Storage media primary key/model/table and FK does v3 installed vendor source provide? Verify before finalizing marketing media schema.
3. How will organization-scoped Kinship roles map to brand-specific permissions: explicit brand grants vs memberships + per-brand roles? Decide with schema and tests.
4. Which provider accounts and app reviews are available, and which social APIs actually permit direct publishing for this business? Document per network, per capability.
5. Which CI provider, staging host, secret store, object storage and queue backend will be selected? Do not put credentials in documents.
6. For MVP, will UI be Blade/Livewire, separate Next.js, or hybrid? Backend and API contract remain stable whichever is selected.
7. Which geographic countries and languages are actively supported initially? Avoid seeding data the product cannot maintain.
8. Which laws, user consent flows, data retention and customer terms apply to each region? Review with appropriate qualified counsel before outbound automation.
9. What are SLOs, model/budget caps, and vendor costs? Decide after baseline measurements.
10. What is the actual supported connection route for this ChatGPT plan/workspace? Verify before implementing a potentially unusable write integration.

## Decision change procedure

Update this ADR table, affected architecture documents, tests and API/migration compatibility notes in the same PR. A new chat must not silently overturn previously documented design choices; it should surface a proposed change with reason, migration strategy and tests.
