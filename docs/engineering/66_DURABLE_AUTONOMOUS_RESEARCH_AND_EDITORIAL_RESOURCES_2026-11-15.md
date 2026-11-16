# Increment 66 — Durable autonomous research and editable editorial resources

**Date:** 2026-11-15 · **Source implemented / Laravel & MySQL runtime unverified** · **Baseline:** Increment 65 full-source archive.

## Why this matters

Increment 65 allowed ChatGPT and other AI clients to authenticate and ask Planner to record *client-supplied* research. This new work adds a **real, optional Planner-side research executor** using the OpenAI Responses API `web_search` tool, plus an AI editorial writing stage. The completed resource can become an ordinary immutable Content Studio draft. The user still decides whether to approve that draft; the client never gets an approval capability.

**Workflow:** owner/manager with active single-brand delegation or scoped MCP agent → `research.autorun` → durable `QUEUED_RESEARCH` → `RESEARCHING` → cited evidence `QUEUED_RESOURCE` → `GENERATING_RESOURCE` → `READY_FOR_REVIEW` → agent/editor `resource.promote` → existing `research.capture`/`content.draft` → independent human approval → existing approved-plan/optional delegated publishing. The two automated provider calls are separate fenced stages, not a single opaque request. `research.cancel` can cancel only queued or generated-but-not-promoted runs, not interrupt an in-flight HTTP request. No agent action can approve its own draft.

## Files and responsibilities

- `database/migrations/2026_11_15_180000_create_planner_agent_autonomous_runs.php`: additive durable runs and append-only events, composite tenant/brand/delegation FK, `(delegation_id,idempotency_key)` uniqueness, state/lease indexes. Do not run `migrate:fresh` against an existing database.
- `App/Repository/Contracts/PlannerAgentAutonomyRepositoryContract.php` and `App/Repository/Eloquent/PlannerAgentAutonomyRepository.php`: scoped queue/get/list, `FOR UPDATE SKIP LOCKED` claims, single-attempt stage fencing, expiry quarantine, bounded resource persistence and cancellation. There is no blind retry after an ambiguous remote call.
- `App/Services/Agents/SearchWithAttributedSources.php`: fixed `https://api.openai.com/v1/responses` destination, configurable model, `web_search` tool, no arbitrary website download, no HTTP retries, no persistence of raw provider JSON or credentials. Requires a completed search-call item **and** bounded HTTPS URL citation annotations. **Citations indicate provenance; they do not establish truth.** Current provider details: https://developers.openai.com/api/docs/guides/tools-web-search
- `App/Domain/Agent/AutonomousResearchEvidence.php`: pure strict source extraction/normalization, cited-result hashing, reject uncited responses. Sources are `verified:false` and limited to twelve.
- `App/Services/Agents/ProcessAutonomousAgentRuns.php`: scheduled bounded executor; rechecks user, active delegation, organisation membership, current Kinship `content_item.store` before and after remote work; uses existing `BrandAiUsageBudget` per provider stage, transactions **outside** the network call, never changes publication jobs.
- `App/Services/Agents/OrchestratePlannerAgent.php`: `research.autorun`, `research.cancel`, and `resource.promote` use normal grant-scoped action receipts and service authorization. Promotion checks **both** research and generated-resource digests then composes existing `research.capture` and `content.draft` in one transaction; no human approvals are created automatically.
- MCP: new `StartAutonomousResearchTool`, `ListAutonomousResearchTool`, `PromoteAutonomousResourceTool`, `CancelAutonomousResearchTool` on the existing governed agent server; no extra OAuth routes. The campaign prompt now recognizes server-side research when enabled, or client-side research when disabled.
- First-party: invokable `StartPlannerAutonomousResearchController`, `PromotePlannerAutonomousResourceController`, `CancelPlannerAutonomousResearchController`; session+CSRF+organisation context+Kinship permission routes in `routes/web.php`; React `AgentDelegationPanel.jsx` create, progress, citations, resource text, promote, cancel. Only delegation owners with active brand permissions can submit; managers can inspect brand work in the original grant UI.
- `app/Repository/Eloquent/PlannerAgentRepository.php`: an agent's listing is now scoped to that delegation, not all brand work orders.
- Eloquent model/factory and unit/feature/real MySQL integration test sources included.

## Release flags / dependencies

All new external execution is disabled by default:

```dotenv
PLANNER_AGENT_ACCESS_ENABLED=false
PLANNER_AGENT_WRITE_ENABLED=false
PLANNER_AGENT_AUTONOMY_ENABLED=false
PLANNER_AI_API_KEY=
PLANNER_AI_RESEARCH_MODEL=gpt-6-astra
# Global publish stop remains ON and independent
PLANNER_EMERGENCY_PUBLISH_STOP=true
```

The research model must be a Responses API model allowed to call `web_search` for the configured account. This makes an external OpenAI API request and can incur research-tool/token charges. `PLANNER_AI_DAILY_BRAND_LIMIT` limits *requests*, not guaranteed USD expenditure. Default provider settings and event evidence omit bearer tokens. No new Composer/npm dependencies were introduced. Existing Passport/MCP dependencies are still declared in `composer.json` and the two lockfiles remain unmodified.

## Local acceptance sequence (run by owner)

1. On PHP 8.4 with required extensions, review lockfiles and use `composer update laravel/passport laravel/mcp --with-all-dependencies` (do not delete lockfiles), then `composer install`, `npm ci`.
2. Point testing at a **disposable** MySQL 8 database. Run reviewed additive `php artisan migrate` and rollback smoke tests. Confirm composite tenant/delegation foreign keys and worker `SKIP LOCKED` with two processes.
3. Run `php artisan test --filter=AutonomousResearchEvidence`, `php artisan test --filter=PlannerHostedResearchGateway`, `php artisan test --filter=PlannerAutonomousResearchRun`, full PHPUnit, `node --test tests/Frontend/*.test.mjs`, and `npm run build`.
4. Verify a real active brand and current owner/manager Kinship permissions and an issued `REVIEW_REQUIRED` agent grant. Enable agent access/write/autonomy **only in staging** with a restricted research API key. Keep all provider publishing release switches OFF.
5. Queue from the operator panel or call scoped MCP `start-autonomous-research`. Run `php artisan planner:process-agent-runs --limit=5` or the scheduled worker. Inspect research citations, resource SHA-256, usage accounting, event history and `READY_FOR_REVIEW`. Convert to a draft, request approval as an agent, and confirm a *separate eligible human* must approve it.
6. Simulate webhook/provider timeouts, malformed JSON, uncited results, revoked delegation between stages, owner role removal, daily request exhaustion, two simultaneous workers, interrupted lease, duplicate run keys, changed payload with the same idempotency key, cross-brand reads, and in-flight cancellation. Ensure `UNCERTAIN` never auto-retries an external request.
7. Verify live ChatGPT OAuth/PKCE/DCR handshake and all MCP tool schemas on your publicly reachable HTTPS staging deployment. Recheck default `PLANNER_EMERGENCY_PUBLISH_STOP=true` and do not enable live posting without separately verified provider permissions and approval release gates.

## Verification performed in this environment

- All new PHP files passed syntax lint; repository boundary, migration structure, Kinship permission catalog and controller convention guards pass (see local execution logs supplied with the session).
- Portable cited-response normalization passed; focused frontend architecture checks pass. Full frontend suite has three *preexisting* parser-module failures due to absent `node_modules/@babel/parser` in this environment.
- **Not executed:** Composer vendor install, Laravel route boot, MySQL migrations or concurrency tests, real OpenAI web-search API, token-budget enforcement in database, browser UI, real ChatGPT MCP OAuth client, real social publishing.
- **Source completion statement:** This closes an important missing autonomous written-resource workflow **in source**, but is NOT evidence the entire Planner platform is production-accepted. Original five-batch estimated source scope was around 97% before this new workstream was added; do not automatically call it 100% or mark any of the five batches accepted. Asset/image generation is not implemented by this text-resource workflow. Pensuh.com public legal/privacy page deployment remains open.
