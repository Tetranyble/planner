# 64 — Governed AI-agent orchestration and explicit delegation

**Date:** 2026-11-15 · **Status:** SOURCE IMPLEMENTED, RUNTIME UNVERIFIED · **Baseline:** Increment 63 full project ZIP.

## Product goal

An authenticated external AI (including a ChatGPT-compatible MCP client) can organize a marketing campaign in Planner: bring *attributed external research* into a brand work order, link safe existing media, write an immutable content draft, create allowlisted social targets, request human review, make a held publication plan **only after real content approval**, and, with distinct explicit owner-delegated authority, arm an already-approved job. Orchestration reuses business services already used by the Planner frontend; it does not contain a second publishing engine. Human approval and provider release gates remain authoritative.

### Scope that is supported in source

1. User opens Planner Content Studio > AI Agent Orchestration, issues a short-lived one-brand credential, and securely configures a trusted AI client's MCP/REST connector. Secret is displayed once and never sent to the model in a prompt. Brand association derives from the Sanctum token record, never client-supplied organisation or brand IDs.
2. Agent researches using **its own externally available browsing/search tools**. Planner `research.capture` does not visit URLs, certify facts, or perform autonomous internet searches; it stores up to 12 HTTPS references, the agent-provided objective/findings, an immutable digest, and explicitly *unverified* provenance.
3. Agent may link an **already uploaded, scanned** Tetranyble Storage asset using `media.link`; no arbitrary upload or image generation is smuggled into this action. The existing Storage package retains media ownership and ACLs.
4. `content.draft` persists brand-owned immutable draft/version, AI-generated classification and existing media references; `content.target` binds that version to an allowlisted account with existing provider editorial checks; `approval.request` invokes the regular review rules (policy snapshot, eligible reviewers, no self-approval). Neither the agent tool nor work order can make an approval decision.
5. `publication.plan` requires valid, explicitly approved immutable target/version and creates an idempotent held plan. **Review Required** agents end here. **Delegated Approved Publish** requires an owner-issued, time-limited grant with named accounts and daily caps, current owner role recheck, existing publication approval, released live provider capability, global emergency stop clear, and current due-job readiness to `publication.arm`. Actual sending remains the existing provider worker, disabled by default.
6. Every action has its own idempotency key with canonicalized payload hash and unique DB index; changed payload under reused key is rejected. Per-brand/delegation DB locks protect count quotas; event and audit rows are written transactionally. Token expiry/revocation immediately prevents new actions. Reviewer can inspect agent research evidence and timeline in a first-party screen.

### Permission modes

| Mode | Tools | Approval | Publishing |
|---|---|---|---|
| `REVIEW_REQUIRED` | Research, link already-safe media, draft, target, request review, plan approved content | Always by eligible independent human reviewer | Held only; operator manually authorizes release |
| `DELEGATED_APPROVED_PUBLISH` | Same plus `publication.arm` | Still independent human review; never AI self-approval | Owner-scoped account allowlist, per-day arm cap, existing kill switches, provider gates, verified content and active owner role |

**No unrestricted auto-publishing.** A user may request explicit delegation, but a grant cannot override the platform's immutable approval model, account authorization, rate caps or a deployment kill switch. No blind replay after ambiguous remote outcome.

## Routes and clients

- First-party human-managed grants: `GET/POST /organisations/{organisation:uuid}/brands/{brand:uuid}/agent/delegations`; `DELETE /organisations/{organisation:uuid}/brands/{brand:uuid}/agent/delegations/{grantId}` (session, CSRF, organisation context, Kinship + service checks).
- Human evidence: `GET /organisations/{organisation:uuid}/brands/{brand:uuid}/agent/work-orders/{uuid}`.
- Scoped REST client: `POST /api/v1/agent/action` with `Authorization: Bearer <securely stored delegated secret>`, body `{"action":"research.capture","idempotency_key":"random-safe-key-at-least-12","payload":{"title":"...","objective":"...","findings":"...","sources":[{"url":"https://example.org/report","title":"..."}]}}`; `GET /api/v1/agent/work-orders`.
- Agent MCP server: `https://YOUR_PLANNER_HOST/mcp/planner-agent` (not necessarily ChatGPT native connect); 7 tools and a reusable `PlannerCampaignWorkflowPrompt`; uses the same service and Sanctum credential. Existing `https://YOUR_PLANNER_HOST/mcp/planner` remains read-only.
- Existing token-isolation middleware denies agent bearer credentials against *all other* `/api/*` mutation routes and denies read-only Planner credentials against agent routes.
- Explicit deployment flags (all **false** by default): `PLANNER_AGENT_ACCESS_ENABLED`, `PLANNER_AGENT_WRITE_ENABLED`, `PLANNER_AGENT_DELEGATED_PUBLISHING_ENABLED`, `PLANNER_AGENT_ALLOWED_ORIGINS`. HTTPS required outside local/testing; Origin checked for browser requests.

### OAuth and current ChatGPT limitation

This is a **manually provisioned Sanctum bearer client** for a trusted external MCP/REST agent; it is not yet a full ChatGPT OAuth connection with login/consent screens. ChatGPT's standard OAuth-based connector onboarding may require an OAuth 2.1 authorization server, PKCE, consent/grant UI, discovery/protected-resource metadata and verified MCP client compatibility. Laravel's official documentation recommends Passport for OAuth flows. **Do not delete lockfiles or install Passport without configuring migration of auth guard and scope semantics.** The assistant in this chat has not authenticated to a Planner deployment. Someone must deploy the server and connect the client explicitly.

### Out of scope / not represented as implemented

- Planner does **not** scrape external sites, autonomously browse, generate images or export social artifacts by itself through the new agent tools. The AI client's research and copy capabilities are external; first-party AI drafting exists separately.
- Automatic approval is prohibited. Actual provider upload/post success, live user-granted provider scopes, browser OAuth, and provider receipt verification remain subject to previously open runtime release gates.
- No background agent loops or scheduled autonomous job orchestrator were introduced in this increment. Agent-driven actions occur on real authenticated client requests. The existing Planner scheduled worker remains separate.
- No production deployment or public Pensuh.com legal/privacy changes occurred.

## Source files

- Additive migration: `database/migrations/2026_11_15_160000_create_planner_agent_orchestration.php` — four indexed/composite-FK tenant-safe tables.
- Models, factories: `PlannerAgentDelegation`, `PlannerAgentWorkOrder`, `PlannerAgentActionReceipt`, `PlannerAgentWorkEvent` and two factories.
- `App/Repository/Contracts/PlannerAgentRepositoryContract`, `App/Repository/Eloquent/PlannerAgentRepository`, provider binding.
- `AgentDelegationPolicy`, `ManagePlannerAgentDelegations`, `OrchestratePlannerAgent`.
- Separate middleware `RequirePlannerAgentGrant`, API bearer isolation update, `routes/api.php`, `routes/web.php`, `routes/ai.php`, `bootstrap/app.php`.
- MCP `PlannerAgentServer`, seven action tools + `PlannerCampaignWorkflowPrompt`.
- First-party `AgentDelegationPanel` and scoped REST/first-party controllers.

## Local acceptance and test plan (not performed here)

1. Review `composer.json` and existing `composer.lock`; resolve `laravel/mcp` version in your own environment. Preserve lockfiles until package solver succeeds; do not remove either file casually.
2. Populate required settings in disposable `.env.testing` with local HTTPS / MySQL and correct Kinship/Storage, run **additive** migrations. Never `migrate:fresh` on a live database.
3. Run `php artisan test --testsuite=Integration --filter=PlannerAgent`, `php artisan test`, and `npm ci && node --test tests/Frontend/*.test.mjs && npm run build`. Verify all package role permissions and BrandScope context are correct in real requests.
4. Test two organisations/two brands with different managers; expired/revoked tokens; wrong Origin, HTTP production rejection, users removed from org/brand; read-only agent route bypass; XSS/CSRF/origin isolation and rate-limit bursts.
5. Check simultaneous requests on same grant cannot exceed `daily_action_limit`/`daily_arm_limit`, including unchanged idempotency-key replay, changed-key conflict, failed target/approval rollback and active approval transitions.
6. Test two distinct human accounts for author vs reviewer; prevent reviewers approving their own content. Confirm delegated owner demotion disables arming and emergency stop overrides any grant.
7. With explicit social sandbox permissions only, test full client MCP tool enumeration and actions, then end-to-end `research.capture → content.draft → content.target → approval.request → HUMAN decision → publication.plan → optionally arm`; inspect audited provider state and receipts. Validate manual-review mode never arms.
8. Confirm deployment logging and secrets inventory exclude Sanctum bearer and social credential plaintext; test token revocation in existing client sessions and invalidate caches.

## Source verification in this environment

- Portable policy assertions: 29 passed.
- Focused frontend integration checks: 6 passed (after owner-role and prompt invariants added).
- PHP lint, architecture/repository, Kinship catalog and migration structure guards: see accompanying handoff and console output; full Laravel/MySQL/MCP/live provider requests remain **unexecuted**.
- Prior full-source estimate ~97% referred to original five batches and **must not be misconstrued as percentage coverage of the newly emphasized external-agent orchestration capability**. All five batches remain unaccepted until user-run runtime gates pass.
