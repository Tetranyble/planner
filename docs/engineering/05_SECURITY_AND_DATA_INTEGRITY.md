# Data integrity, authorization and privacy controls

## Tenant invariants

- Organisation is the Kinship workspace; brand is finer-grained marketing boundary.
- User must be authenticated, current organisation context validated as active membership, then Kinship permission verified, then brand membership and relevant action/policy verified.
- Every child row's organisation/brand references must be composite-constrained wherever possible. No create/update cross-links to content/media/approval/lead in another brand.
- Required auditing: actor (user/service/AI tool), subject, organisation, brand, action, request ID, before/after summary or hashes, timestamp; never log secrets or unrestricted personal data.
- Redis locks alone do not define database transaction isolation. Use unique keys, row locks and guarded transitions on MySQL; verify with actual concurrency tests.

## Publishing approval invariants

1. The approval references **exact target revision and payload hash**; new revision invalidates it.
2. Self-approval prohibited if applicable policy requires independent authorization; optional two distinct reviewers separate from creator.
3. Publishing worker revalidates approved revision, brand/organisation, connection capability, media readiness and cancellation before external side effect.
4. Uncertain provider response triggers reconciliation, never unbounded blind retry.
5. Tool write actions are subject to exactly the same checks as the UI/API and are audited as actor+delegated identity.
6. Provider access terms are documented per account and integration (some networks require assisted/manual publishing).

## DB/migration release rules

- Development fresh schema changes are not automatically safe upgrades; deployed systems receive forward-only additive migrations, backfill, validation, then constraint enforcement, then removal in a later release.
- Every migration includes FK index coverage, on-delete choice, default, nullable semantics, character set/collation, enum consistency and rollback impact.
- MySQL 8.x supports generated markers and composite FKs but each constraint must be verified on the actual engine/version in integration tests; no assumptions based on previous PostgreSQL design.
- Distinguish business JSON (normalize when stable and constrained) from raw provider/webhook/tool payload snapshots (JSON with versioning, retention and schema validation).
- Country/state/city containment must be validated, including nullable targeting "entire state/country" semantics; official administrative divisions differ by country.
- Data migrations include row count/checksum comparison and separate handling for invalid historic rows.

## Kinship and Storage ownership

- Kinship remains authoritative for role definitions, user permissions and workspace-scoped assignments. Application membership records define eligibility and business state; integration tests assert both stay in sync after grant, revoke and user switching.
- Tetranyble Storage is authoritative for original objects/derivatives and access/quota mechanisms. Marketing tables store brand association and references. Validate physical object links and permissions; do not expose package routes without gate tests.
- Never store raw OAuth tokens in marketing domain tables or AI tool metadata. Keep encrypted secret references, rotate and revoke; use narrow provider OAuth scopes.

## Marketing data and contact restrictions

- Do not ingest Pensuh student educational/child pickup data into marketing analytics; only approved business conversion events such as demo requested, pilot activated and paid signup.
- Persist consent purpose, channel, source, timestamp and withdrawal/suppression status; enforce at **dispatch time**, not only sequence creation.
- Honor unsubscribe/objection, maintain separate suppression lists, avoid harvesting individuals' private contact info; restrict employee access to leads.
- Retention rules vary for contact info, raw webhook payloads, audit/financial entries and user-uploaded media; preserve legal holds.

## Security test scenarios to automate

- Wrong `organisation_id` and correct guessed `brand_id`; brand from another organisation; revoked role with cached permissions; consent withdrawal just before dispatch.
- Login session swap/request scoped context leak in Octane/queue worker; wrong subject bound to external MCP tool.
- OAuth state/replay and token expiry, storage path traversal, content type spoofing, provider webhook replay, automation/AI tool injection, archive zip bomb, URL fetch SSRF.
- Request retries with a repeated Idempotency-Key, duplicate external callback, race on unique live post occurrence.
