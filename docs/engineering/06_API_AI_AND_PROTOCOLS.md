# Versioned APIs, UTCP and ChatGPT MCP

## Contract-first interfaces

- Canonical versioned REST API `/api/v1/...`, with explicit OpenAPI specification, request validation, authorization and Eloquent Resources.
- Avoid leaking Eloquent model internals or provider tokens. Standard errors: stable code/message, field errors, request ID, retry guidance when appropriate. Pagination/sort/filter explicit.
- Mutations performing externally visible side effects require idempotency key, business approval where needed and typed job/status response.
- Publish *curated*, read/write-scoped UTCP tool metadata referring to these REST operations; no extra proxy in UTCP-native clients.
- MCP compatibility adapter for ChatGPT maps tool names to the **same Laravel application services**, retaining existing authorization, approval and transaction boundaries. It is not a parallel marketing engine.

## Initial REST resources (not yet implemented)

| Resource | Example API | Authorization |
|---|---|---|
| organisations | `GET/POST /api/v1/organisations` | authenticated and relevant Kinship permission |
| members | `POST /api/v1/organisations/{id}/members` | `membership.manage` and same org |
| brands | `GET/POST /api/v1/organisations/{id}/brands` | org + brand membership as applicable |
| knowledge | `GET/POST /api/v1/brands/{id}/claims` | verified brand governance role |
| social accounts | `GET /api/v1/brands/{id}/social-accounts` | `social-account.view` and brand access |
| campaigns | `GET/POST /api/v1/brands/{id}/campaigns` | `campaign.manage` + brand access |
| contents | `POST /api/v1/brands/{id}/content-items` | `content.create` + brand access |
| approvals | `POST /api/v1/targets/{id}/approval-requests` | content/approval policy |
| scheduling | `POST /api/v1/targets/{id}/schedule` | approval+capability+schedule role |
| publication | `GET /api/v1/brands/{id}/publications` | publication read permission |
| leads | `GET /api/v1/brands/{id}/leads` | lead read permission + privacy scope |
| analytics | `GET /api/v1/brands/{id}/analytics` | analytics view permission |
| AI runs | `POST/GET /api/v1/brands/{id}/ai-runs` | AI permission + budgets |

**Caveat:** permission spellings must match the actual `config/kinship.php` catalogue after verification; table above is illustrative API surface, not existing routes. Never expose a new endpoint by simply copying examples without testing policy guards.

## UTCP discovery lifecycle

1. Define tool schemas with explicit input and output types and OpenAPI route refs.
2. Validate only authorized brand/org IDs are reachable; tool-list policy may filter by principal scopes.
3. Include HTTP auth requirements and idempotency in tool definition; never embed secrets.
4. Contract-test UTCP manual against live OpenAPI schema and endpoints; version when breaking.
5. UTCP client calls REST directly under its delegated actor credentials; server rechecks all rights.

## MCP adapter lifecycle

1. Implement OAuth or compatible delegated authentication as supported by the deployed ChatGPT custom integration; verify availability in target account/workspace.
2. MCP tool discovery advertises only implemented operations. Map tool arguments to validated service DTOs. No raw SQL, unrestricted URL fetching or arbitrary code tool.
3. Read tools: `list_brands`, `get_brand_profile`, `list_campaigns`, `get_calendar`, `get_publication_status`, `get_campaign_analytics`.
4. Draft tools: `create_campaign_draft`, `generate_content_drafts`, `submit_for_approval`.
5. Controlled write tools: `schedule_approved_target`, `cancel_eligible_publication`, `create_approved_automation` — always gated by permission + approval hash + idempotency.
6. Long operations return durable run ID and current state. Never return `published` from merely queued work.
7. Log invocation ID, authorized actor, client, scopes, brand, idempotency hash, outcome and latency; redact sensitive payloads.
8. Test ChatGPT call path separately from ordinary REST and internal AI tooling; if ChatGPT cannot support custom write connectors under the user's plan, native app remains functional. Do not claim a connection exists before successful handshake.

## AI tool execution policy

| Risk | Rule |
|---|---|
| Read brand performance | eligible delegated read scope; structured logs |
| Generate drafts | draft-only, output validated, source provenance preserved |
| Change brand policy | privileged authorisation + explicit review and audit |
| Approve content | only eligible human; AI never forges a human decision |
| Publish externally | valid human-approved immutable payload + authorized actor/scheduler |
| Outreach a lead | documented lawful basis/consent and suppression rechecked at dispatch |
| Delete media/data | privileged destructive policy, retention/legal hold evaluation |

## Protocol acceptance tests

- Metadata schema is valid; every advertised tool calls a real implemented endpoint/service; unknown tools refused.
- OAuth wrong-client/expired/revoked/wrong-brand tokens denied, no cross-user state.
- MCP and REST produce equal outcomes and errors for the same actor and action.
- Tool replay produces idempotent result, never a duplicate public post.
- AI request to schedule without human approval returns pending/denied rather than succeeding.
