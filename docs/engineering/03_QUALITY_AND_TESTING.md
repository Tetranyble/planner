# Quality assurance strategy — mandatory

## Non-negotiable quality standard

Every business feature is test-led and has documented success, authorization, invalid-input, cross-tenant, concurrent, recovery and external-dependency failure paths as applicable. **No phase is complete because code merely parses.** Use real MySQL for persistence behavior. Preserve `composer.lock`, pin dependencies, and report exact pass/fail counts from executable test runs. Do not use `--stop-on-failure` as a substitute for a full run in CI. No skipped or flaky tests in a release branch without documented triage.

## Test layers

| Layer | Scope | External dependencies | Examples |
|---|---|---|---|
| Pure unit | Policies, DTOs, state transition tables, claim validation, scheduling calculations, permission rule evaluation | None | approver cannot self-approve; expired approval rejected |
| Service unit | Service coordinating repository contracts and ports | Mock contracts, fake clock | scheduling triggers outbox once; unsupported provider rejected |
| Feature (Laravel) | Requests, validation, guards, resource output, jobs/events | Laravel testing DB, faked providers | brand endpoint 403 on another brand; admin vs member |
| Integration DB | Real MySQL migrations, FK/unique/check semantics, transaction/locking, repository SQL | Disposable MySQL 8.x | uniqueness, tenant FK, lease contention |
| Package integration | Actual Kinship/Storage installed code and migrations | MySQL + local storage disk | grant cache isolation; workspace-scoped media access |
| Adapter contract | Social HTTP schema/error mapping, OAuth refresh, webhook verification | Recorded sanitized fixtures and HTTP fake | 429 Retry-After; expired token; unknown outcome |
| E2E staging | Browser to DB/job, authenticated tool calls, real provider sandbox where allowed | Isolated staging | draft > approve > schedule > publish or assisted handoff |
| Security/performance | AuthZ, SSRF, replay, rate limits, concurrency, storage quotas | Staging/test env | cross-tenant IDs, webhook replay, burst jobs |

## Test environment policy

- Primary schema and tenancy tests run on **MySQL 8.x**, not an SQLite surrogate. `phpunit.xml` currently has DB connection lines commented out; configure isolated test DB credentials via CI/`.env.testing` outside version control. `DB_CONNECTION=mysql`, distinct database/user, **never production**.
- Tests use `RefreshDatabase` only against a disposable database; use `DatabaseTransactions` where appropriate. Parallel workers require separate databases or Laravel-supported test isolation.
- Unit tests without persistence need no DB. Never hit real networks in unit/ordinary feature tests.
- Providers are faked at HTTP boundary, with representative redacted fixtures; sandbox integration tests are opt-in with safe credentials.
- `QUEUE_CONNECTION=sync` aids determinism for some feature tests but masks concurrency; run separate queue-backed integration tests with actual workers/storage.
- Test time using injected clock or Carbon's test clock; never sleep arbitrary real seconds to wait for scheduled jobs.
- Use factories, fixtures, fixed provider responses; avoid nondeterministic Faker values in assertions.

## Per-feature testing matrix (minimum)

1. **Happy path**: valid authenticated actor completes operation; persisted state and response match contract.
2. **Validation**: nulls, malformed values, excessively long inputs, invalid enums, unsupported social media formats.
3. **Authorization**: unauthenticated 401; insufficient permission 403; wrong organisation/brand 404 or 403 consistently; suspended/revoked actor; user switches organisations; no cached permission leak.
4. **Data integrity**: unique/FK/not-null/constraints, immutable revisions, orphan prevention, transaction rollback.
5. **Race/retry**: concurrent duplicate request, idem key replay, lost worker lease, two workers claiming same job, transient provider 429/5xx, request timeout after provider acceptance.
6. **Operational**: structured logs/correlation ID, outbox status, failed job DLQ or terminal handling, retry/alert rules.
7. **Security/privacy**: token redaction, secret leakage, unsafe file type, webhook replay, lead consent/suppression enforcement, data deletion/retention restrictions.

## CI pipeline — required stages

1. `composer validate --strict` and `composer install --no-interaction --prefer-dist`; verify PHP extensions.
2. `vendor/bin/pint --format agent` in development; in CI run a non-mutating style check appropriate to installed Pint version.
3. `php -l` for changed PHP, plus `php artisan config:cache`/`route:list` in a controlled environment.
4. `php artisan test --testsuite=Unit`; then `--testsuite=Feature` against disposable MySQL.
5. Add and execute an explicit Integration suite under `tests/Integration` once configured in `phpunit.xml`; includes package migrations and composite FK checks.
6. Run `python3 scripts/check_migration_structure.py` as **supplemental** static validation only.
7. Execute `php artisan migrate:fresh --env=testing --database=mysql --force` only with positively verified **disposable** credentials. Test rollback + reapply separately.
8. Test queued work with Redis/database queue and at least two worker instances; failure/recovery suite.
9. Dependency/security checks (e.g., Composer audit, secret scanner, review of vulnerable libraries), API OpenAPI conformance, artifact build.
10. Staging smoke tests and release checklist; production rollout with approval and rollback plan.

## Coverage policy

No invented coverage percentage. Establish an instrumented baseline, then track coverage for critical domain services and error branches. Suggested objectives: >= 90% branch coverage for pure policy/state-machine modules, >= 80% line coverage for domain service logic, and 100% scenario coverage of explicitly listed release-critical security/idempotency invariants. **Meeting numeric coverage alone never substitutes for meaningful failure tests.** Tooling must support coverage reporting (PCOV/Xdebug) before stating values.

## Evidence expected in every PR

- Work item ID and acceptance checklist; affected migration versions and tenant scope.
- Repository contract and DI binding; unit/feature/integration tests; test commands and output.
- Proof the endpoints and workers deny cross-tenant and unauthorised access.
- Database migration-up/down result and data migration strategy when necessary.
- Provider contract fixture changes; operational events/metrics; failure semantics.
- Documentation, `PHASE_TRACKER.csv` and `CONTINUE_HERE.md` updated.

## Stop-ship conditions

Real tenant leak; raw provider tokens in logs; unverifiable publish approval; duplicate/unknown provider publishing replay without reconciliation; unaudited privileged tool write; failed real-MySQL migration; failing/disabled required tests; unrecoverable queue loss; unreviewed production destructive migration; noncompliant outreach; missing consent controls; missing rollback instructions.
