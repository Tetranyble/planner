# Batch 1 (part 1): durable, held publication intentions — Increment 36

**Source baseline:** Increment 35, `Planner_Phase4_Publication_Readiness_Diagnostics_2026-11-14.zip`.
**Boundary:** These records are operator planning intentions, **not an active scheduler or publishing engine**. The `publication_jobs` table exists in the baseline. No new migrations are required. Actual delivery to Facebook, Instagram, LinkedIn, X, TikTok and YouTube is prohibited by design until provider permissions/quotas, dispatch state machine and full runtime gates pass.

## Delivered as one integrated slice

1. `PublicationIntentRepositoryContract` and scoped Eloquent implementation. Uses existing `publication_jobs` table, uniqueness `(organisation_id,content_target_id,occurrence_key)`, and `AWAITING_USER` state with `max_attempts=0`. Atomic writer does not create `SCHEDULED` jobs.
2. `ManagePublicationIntents` service: double authorization, org→brand locks, per-approval exact target check, live readiness recomputation under the write transaction, explicit timezone/UTC normalization, 5-minute to 1-year planning horizon, idempotency matching exact approval+run_at, audit on write and cancel.
3. Safe-content prerequisites are mandatory. Ineligible version, approval, active policy, hash/quorum and media cannot be planned. Connection/capability/credential gaps are permissible only for a *held* record; unknown blocker codes fail closed. This never grants dispatch permission.
4. GET brand-level held publication queue, POST schedule intention for exact item/target/approval, POST cancellable hold. The routes use existing Kinship catalogue permissions and recheck independently at the service boundary. CRUD/invokable controller conventions maintained.
5. React content review operator panel: explicit local date/time, create held intent, list matching held plans and cancel. Readiness/authorization failures appear in the UI. A read-only artisan `planner:inspect-held-publications` scheduled every 15 minutes reports overdue manual holds and does not transition jobs or call providers.
6. PHPUnit pure-rules and real-MySQL integration test sources, frontend architecture tests, progress tracker. New model casts protect time serialization. No reliance on DB facade raw test inserts.

## API contracts

- GET `/api/v1/organisations/{organisation:uuid}/brands/{brand:uuid}/content/publication-intents` — scoped to org+brand, 100 earliest rows.
- POST `/api/v1/organisations/{organisation:uuid}/brands/{brand:uuid}/content/{itemUuid}/targets/{targetUuid}/approvals/{requestId}/publication-intents` — `{occurrence_key: UUID, run_at: ISO-8601 with timezone}`. A repeat with the same key, target, approval and instant returns the same held plan; a conflicting use of the key returns 409.
- POST `/api/v1/organisations/{organisation:uuid}/brands/{brand:uuid}/content/publication-intents/{jobId}/cancel` — idempotent cancellation of held plans only, never running jobs.

`can_dispatch` is unconditionally false. It must NOT be used to enable a provider executor later without separate final dispatch-time checks.

## Executed evidence (local)

- PHP syntax check of new PHP sources: PASS.
- Node frontend architecture focused suite including readiness and approvals: 12/12 PASS.
- Dependency-free `PublicationIntentRules::canHold(['PUBLISHING_ENGINE_NOT_IMPLEMENTED'])` smoke: PASS.
- PHPUnit/MySQL, Kinship live package checks, Storage canonical hash and real browser workflow: NOT RUN. Provider permission app reviews and real API calls: NOT RUN.

## Known risks and next engineering steps

- Pending real MySQL integration and security tests, Vite build and authentic user/role API E2E. Existing baseline Phase 0 gates remain open. This operator planning desk must not be called a scheduler until staging validation.
- Next batch stage: introduce a provider-specific, verifiable capabilities matrix and adapter contract; add durable lease/acquire/revalidate/recovery state transitions with explicit environment kill-switches. Any ambiguous provider response must become `UNCERTAIN` and require reconciliation, never blind retries. Only enable provider I/O after per-provider live review, validated scopes/quotas and controlled staging tests.
- Further stage: true publishing calendar, robust scheduler, campaign/rule automation, provenance, analytics and CRM integrations.
- Pensuh.com privacy/legal and data deletion pages remain tracked and NOT DEPLOYED.

## Provider capability matrix and baseline readiness correction

The existing six OAuth adapters' own capability names are now mapped via `ProviderPublishCapabilityMatrix`: Facebook/LinkedIn/X `post.publish`, Instagram `media.publish`, TikTok/YouTube `video.publish`. **This mapping is not entitlement certification.** The repository continues to require an account-scoped `AUTOMATIC` capability with unexpired evidence; all currently installed adapters remain unavailable/assisted for publishing. Corrected the baseline connection health test to use `ProviderConnectionStatus::CONNECTED` (the actual enum), not the nonexistent `ACTIVE` connection state. Neither change enables a provider executor.

- New pure matrix PHPUnit test source and 1/1 focused Node architecture assertion PASS.
- Full frontend Node suite: 75/78 PASS; three pre-existing test files fail to load `@babel/parser` (dependency not installed in this environment). No claim of complete frontend suite pass.
