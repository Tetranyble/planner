# Increment 44 — Brand-aware AI suggestions and human draft promotion

## Delivered source
- Optional Responses API text generation with configured `PLANNER_AI_API_KEY` and `PLANNER_AI_MODEL`; no key means no call or suggestion.
- Bounded brand context: brand positioning, default active voice, up to eight approved unexpired claim texts, editor-supplied topic/platform/campaign.
- POST/GET `.../content/ai-suggestions`; POST `.../content/ai-suggestions/{suggestionUuid}/promote`.
- Tenant-bound repository, migration, Eloquent model/factory, audit, Laravel unit-test source, React content studio panel.
- Human promotion creates an ordinary immutable content draft with `ai_generated=true` and `includes_sensitive_claim=null`, no target, approval, publication job, or social network side effect.
- External AI request outside SQL transaction; result SHA-256 recorded. No prompt or API token in audit metadata.

## Risk and validation gates
- API model output MUST be human fact-checked; model is not a verification source. Review AI provider privacy, retention and legal terms before sending brand context.
- Runtime Laravel/MySQL/Vite/browser tests **not performed** here. No production-ready claim.
- Run `php artisan migrate`, `php artisan test --filter AiDraftGatewayTest`, and the full suite on a disposable MySQL DB. Connect a real key only after privacy review.
- Existing publishing flag remains disabled. Pensuh.com privacy/legal workstream is still not deployed.
- Laravel may require locally restoring `.env.example` from your original project environment template; this archive does not contain one. Add `PLANNER_AI_API_KEY=` and `PLANNER_AI_MODEL=gpt-4.1-mini` to your .env.

## Five batch acceptance
1 Publishing Engine: OPEN; live dispatch unimplemented.
2 Content Operations: OPEN; existing studio and approval flows remain runtime-unverified.
3 AI Marketing Intelligence: OPEN; this is one integrated generation/review slice, not a full campaign intelligence system.
4 Analytics & Automation: OPEN; internal workflow metrics only.
5 Integrations & Production: OPEN; production provider tests and UTCP/MCP/deployment remain.
