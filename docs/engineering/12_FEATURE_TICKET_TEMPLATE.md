# Feature / work-item template (copy for each ticket)

## Header

- **Task ID / phase**:
- **Title**:
- **Business objective and user persona**:
- **Prerequisites / package reuse review**:
- **Scope and exclusions**:
- **Tenant / brand scope and permission**:
- **Workflow state transitions / invariants**:
- **Data model (migration and foreign keys)**:
- **API / UTCP / MCP exposure**:
- **Observability and privacy requirements**:

## Implementation plan

1. Inspect sibling repository contracts, Eloquent implementation, services and tests.
2. Write tests for domain rules, HTTP authorization and real-MySQL repository behavior; add provider contract tests where relevant.
3. Add/fix schema with safe migration strategy and factories.
4. Implement typed repository contract and Eloquent implementation under `app/Repository`; bind it in the service provider.
5. Implement application service and authorization checks; use transactions/outbox for multi-table operations.
6. Add controller/Request/Resource/route or job/adapter that calls the service (no duplicate business logic).
7. Add telemetry and error handling; privacy/credential redaction.
8. Run focused and full affected suites, static/style checks, integration tests and staging smoke where relevant.
9. Review/merge and update roadmap/tracker/handoff.

## Acceptance tests (each must have test name and result)

- [ ] Happy path
- [ ] Input validation and boundary values
- [ ] Authentication and Kinship permission
- [ ] Organisation and brand isolation
- [ ] Database FK/unique and transaction rollback
- [ ] Idempotency/retry/concurrency (if side effects)
- [ ] Provider outage/quota/error (if network)
- [ ] Approval/revocation/consent (if applicable)
- [ ] Audit log and telemetry
- [ ] Staging smoke and rollback instructions

## Proof for completion

- Commands and full pass/fail output:
- Commit SHA and PR:
- Migration tested against database/version:
- Package version / external API version:
- Remaining risks:
- Handoff update link:
