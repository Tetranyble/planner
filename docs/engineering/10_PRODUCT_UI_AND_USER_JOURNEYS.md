# User journeys and application surface inventory

**This is a design/build checklist**, not an assertion that these screens or endpoints already exist. The current routes are Laravel boilerplate.

## User personas and access

- Platform operator: monitors provider uptime, queue lag, per-org usage, service errors; cannot silently bypass tenant authorization.
- Organisation owner/admin: onboard brands, manage members and roles, configure policies and connected social accounts.
- Brand manager/editor: build campaigns, draft content, schedule when authorized, view analytics for assigned brands.
- Brand reviewer: approve exact version/hash based on independent approval rules.
- Marketing analyst: read permitted campaign/lead/attribution views without write rights.
- AI-agent / ChatGPT delegate: scoped system identity acting for an authenticated principal, with explicit tool permissions and approval gates.

## Screens by module

| Screen / route concept | Phase | Must include | Permission / safety check |
|---|---:|---|---|
| Authentication and account selection | 1 | sign-in, org switch, states, logout | verified subject/session, revoked membership |
| Organisation setup | 1 | create organisation, owner, preferences | bootstrap ownership and uniqueness |
| Organisation members | 1 | invite/member list/grant/revoke | Kinship workspace and audit |
| Brand directory | 1–2 | list/add/edit/suspend, roles | brand membership |
| Brand knowledge/voice/claims | 2 | audience, terms, evidence, review | verified claims and source provenance |
| Social accounts | 3 | connect, choose Page/channel, scopes, status | OAuth state, revocation, explicit brand linking |
| Media library | 4 | upload, preview, variant readiness, bulk select | Storage workspace quota/access and file validation |
| Campaigns | 5 | list/create, objective, dates, KPI goal | brand-scoped CRUD and pagination |
| Content studio | 5 | draft/editor, platform target tabs, media, version history | no edited post inherits prior approval |
| Publishing calendar | 5–8 | week/month/list, filters, timezone | only permitted accounts/brands visible |
| Approval inbox | 6–8 | exact preview, approver decisions, reason | four-eyes, policy version, expiry |
| Publication board | 7–8 | scheduled, retry, published, uncertain, failure | no blind requeue, error visibility |
| Automation builder | 9 | triggers, actions, test/simulation, toggle | versioned rules and protected actions |
| AI content brief | 10 | research, idea batch, generate drafts, sources | cite verified claims, show cost and approval state |
| ChatGPT integration settings | 11 | delegated clients, scopes, revoke, logs | no missing-tool write bypass |
| Leads and demo pipeline | 12 | contact list, lead lifecycle, booking follow-up | consent, access, suppressions |
| Analytics overview | 13 | funnel, campaign KPIs, channel breakdown | attribution definitions and source currency |
| Logs/usage/system health | 14 | outbox, queues, webhook health, quotas, audit | least-privilege operator access |

## Critical user journeys

### 1. Pensuh manager publishes a scheduled educational post

Login -> choose eligible organisation -> open Pensuh brand -> create campaign -> upload/reuse Storage-backed media -> create content item/version -> customize target for permitted channel -> preview and validate claims -> submit approval -> independent reviewer approves exact payload -> manager schedules -> DB persists occurrence/outbox -> worker calls provider -> provider ID/status reconciled -> UI shows success or actionable exception -> attribution/tracking links flow to analytics.

**Expected negative path:** edited draft after approval invalidates request; scheduling is denied until reapproved.

### 2. AI prepares a monthly cross-platform batch

Request via native UI or supported ChatGPT MCP -> verify delegated org/brand permissions -> retrieve approved knowledge and relevant insights -> create durable `ai_run` -> propose topics and content drafts -> record source provenance and cost -> save as drafts -> human approves exact targets -> scheduler publishes only eligible accounts -> weekly report and operator alerts.

**Expected negative path:** AI-generated unverified claim or inaccessible source is flagged and cannot be automatically published.

### 3. School requests a Pensuh demo

Consent-aware website request -> signed product event to Planner -> dedupe/conversion event + lead -> appropriate lead owner assigned -> confirmation + compliant follow-up workflow -> demo booked/attended -> pilot/paid conversion event -> attribution dashboard.

**Expected negative path:** withdrawal or suppression before a queued promotional send prevents dispatch.

### 4. Organisation switches to second brand

Select second organisation/brand -> server revalidates active membership and Kinship grants -> new brand-specific list with separate permission and cached data boundaries -> operations remain scoped across REST and worker and MCP.

**Expected negative path:** stale first-brand payload ID or cached AI tool scope cannot access second-brand content.

### 5. Provider accepts publish but response times out

Worker records attempt -> timeout classified `UNCERTAIN` -> publication blocked from blind automatic replay -> reconciliation probes remote post identity or requests manual review -> state finalised exactly once in business records -> alert closes.

**Expected negative path:** any second publish request using same occurrence key is blocked until reconciliation.

## UI acceptance standards

- No decorative disabled buttons pretending functionality; controls map to implemented API operations.
- Explicit loading, success, empty, validation, denied, retry/uncertain and disconnected-account states.
- Keyboard accessible controls, semantic labels, small screen support, timezone-aware time input, confirmation for destructive/external effects.
- All screens integrate typed DTOs/Resources and server-enforced permissions; permission checks in UI only supplement backend checks.
