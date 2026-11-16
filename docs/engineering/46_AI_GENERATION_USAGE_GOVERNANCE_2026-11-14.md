# Increment 46 — Shared per-brand AI usage governance

## Implementation
- One additive MySQL ledger `brand_ai_usage_reservations` with tenant+brand FK, model/factory, repository interface and Eloquent implementation.
- A single `BrandAiUsageBudget` governs both brand copy and campaign proposal generation. Reservations serialize on an actual brand row lock, with a bounded daily UTC allowance (`PLANNER_AI_DAILY_BRAND_LIMIT`, default 20). In-flight and completed successful requests consume capacity. Failed requests release capacity. A generated output hash and model are stored on success; no prompts, tokens, provider response bodies or credentials enter the usage ledger.
- Network requests happen outside SQL transactions. A request reserves before network I/O and settles after the normal content/proposal persistence transaction. A crashed process may leave a RESERVED record counting against that day's budget; it is deliberately conservative and needs manual investigation instead of automatic credit release. The `expires_at` field is an operator signal, not automatic quota forgiveness.
- Permission-protected `GET /api/v1/organisations/{organisation:uuid}/brands/{brand:uuid}/content/ai-usage`, with internal BrandPermissionGuard reauthorization. Both React panels display UTC usage/remaining capacity.

## Safety and known trade-offs
- UTC day limits generation *requests*, not actual provider token spend. Token-based monetary limits require measured provider usage, pricing, cost reporting and per-organisation billing contracts.
- If provider generation succeeds but downstream persistence fails, quota is released, although the provider may still bill. Fail-closed reservation behavior and rate limits reduce abuse; billing-grade reservation settlement is future work.
- No automatic social posting or approval bypass. Independent campaign reviews and content approvals unchanged.

## Tests and local acceptance
1. Run `php artisan migrate` on a disposable MySQL database (never production `migrate:fresh`).
2. `php artisan test --filter BrandAiUsageBudgetTest` and full unit/feature/integration suite.
3. `node scripts/check_ai_usage_budget.mjs`; repository, Kinship and controller checks; `npm ci && npm run build` on an appropriate machine.
4. Concurrently submit > configured limit via two users to one brand; verify quota never oversubscribes; verify another brand is unaffected; verify provider failure releases reservation; verify cross-tenant GET is denied.
5. Verify no secret or prompt text appears in usage records or logs, and ensure large AI inputs do not exfiltrate personal data.

## Batch acceptance
This is Batch 3 source progress, **not** Batch 3 completion. Batch 1 live publishing, Batch 2 calendar/media workflows, Batch 3 cost-based quotas/recommendations, Batch 4 external analytics/automations and Batch 5 production integrations remain outstanding. All Laravel/MySQL runtime checks and provider requests are deferred to the user's system.
