# Increment 45 — AI campaign proposals and independent review

## Scope delivered
- Generate bounded 1–30 day cross-platform editorial outlines from existing opt-in AI gateway, approved current brand claims and active brand voice. Produces suggested objectives, KPIs, risks and editorial tasks; no provider posting.
- Add tenant/brand-scoped `ai_campaign_proposals` with proposal digest, source prompt digest, generation model, requested actor and immutable review disposition metadata.
- Add `AiCampaignProposalRepositoryContract` and Eloquent implementation, Eloquent factory, service authorization at HTTP edge and inside service, REST listing/create/review, and content-studio React operator panel.
- Independent owner/manager review: author cannot review own proposal, review is single use, digest integrity is revalidated under DB lock. Rejected and approved proposals remain historical evidence, not executable campaigns. No content drafts are created automatically and every resulting piece still needs content approvals.
- Generation HTTP request happens outside SQL transaction; no network attempt if AI API key unconfigured. New service audit metadata records hashes and disposition only, never prompt text or secrets.

## Local validation steps
1. `php artisan migrate` on a disposable MySQL 8 DB; verify FK and rollback.
2. `php artisan test --filter AiCampaignProposalRepositoryTest` and `php artisan test --filter CampaignProposalContractTest`; run full tests.
3. `node scripts/check_ai_campaign_proposals.mjs`; `php scripts/check_repository_boundaries.php`; `php scripts/check_kinship_catalog.php`.
4. Browser: separate author/editor and reviewer; confirm author cannot review, cross-tenant references return not found, REVIEWED proposal cannot be reviewed again, disabled AI key yields an actionable error without DB write. Verify the source `AiCampaignProposalsPanel.jsx` and `Content/Index.jsx` render.
5. Validate AI API privacy agreements before entering brand data; never send personal data in campaign inputs.

## Safety and limits
- No real provider publishing, campaign execution, auto-scheduling, or verified marketing metrics.
- Models may invent details: every campaign claim and KPI must be reviewed against trusted evidence. Approval of a strategy outline is **not** approval of any content version or publication intent.
- Actual Laravel, MySQL, Kinship runtime, Vite and browser E2E are **not verified** in this container.
- Open batch gates: Batch 1 publishing integration; Batch 2 full media/scheduling; Batch 3 usage budgets and expanded grounded strategy; Batch 4 external analytics and automation; Batch 5 UTCP/MCP, hardening and deployment. Pensuh.com legal/privacy site updates remain not deployed.
