# Increment 67 — Private image generation, human resource revision, uncertain AI spending

**Date:** 2026-11-15 · **Baseline:** Increment 66 full source · **Status:** SOURCE IMPLEMENTED, not runtime/production accepted. The five original batches remain **0/5 accepted**.

## Delivered source

### 1. Actual, disabled-by-default OpenAI creative-image preview

- `app/Services/Creative/GeneratePrivateCreativePreview.php` calls the fixed `POST https://api.openai.com/v1/images/generations` endpoint with `gpt-image-2.5-flare` (model configurable). One original PNG per invocation, medium quality, dimensions limited to 1024×1024, 1536×1024, or 1024×1536. The model and exact parameters require access and staging verification in the owner account. API contract: https://developers.openai.com/api/reference/resources/images/methods/generate.
- The provider's base64 is decoded to bounded bytes. PNG signature, chunk structure/CRC, actual size and SHA-256 are validated. Raw provider payloads and API tokens are not logged or returned as JSON. No HTTP retry after an ambiguous remote request.
- `DownloadPrivateCreativePreview` enforces current organisation membership, brand-scope `media_asset.store`, the independent `PLANNER_CREATIVE_IMAGE_ENABLED=false` feature switch and the existing per-brand AI request budget. It rechecks permissions before returning bytes. The session/CSRF-protected first-party route is throttled to two requests per minute and responds as an `image/png` **private no-store attachment** with a digest header; there is no public URL.
- React `PrivateCreativePreviewPanel` in the existing Content Studio has a brief, aspect selection, preview and download, with Object URLs revoked on change/unmount. The image is not persisted in Planner or a publication queue. **The operator must review/download it, upload it through the existing Tetranyble Storage flow, await scanning/approval, and explicitly link that canonical media asset.** This is deliberately *not* an autonomous binary-to-Storage ingest path; no new direct filesystem uploader or unverified `Media` lifecycle has been introduced.
- The service does not permit MCP agent tools to generate/attach image bytes. Source-only work has NOT verified provider availability, billing, actual browser memory behavior, or a safe automated Storage import.

### 2. Human-controlled corrections before immutable Content Studio promotion

- New additive migration: `2026_11_15_190000_add_human_revision_to_autonomous_runs.php`, increments a bounded `resource_revision_count` after the original run tables (no rewrite or destroy of existing run records).
- First-party `POST .../agent/autonomous-runs/{uuid}/revise`: current owner/manager of the issuing delegation, `content_item.store` and agent access/write flags. It is not exposed as an MCP or delegated-agent action.
- The repository uses scoped grant/organisation/brand lookup and a locked row with **expected_resource_hash compare-and-swap**. Only `READY_FOR_REVIEW` (not drafted/cancelled/in-flight) may be revised. Each successful change appends an event carrying old/new SHA-256, researcher evidence hash, actor, user-supplied reason and monotonic revision number (max 12). Text and hash change together. Research/citations are not rewritten.
- React inline editor keeps unsaved corrections from being promoted. Saving re-fetches the updated digest and count; stale requests return 409. Actual Content Studio promotion remains a separate action that creates an unapproved draft; only an independent human approval can advance publishing.

### 3. Ambiguous external API spending cannot evade the request quota

- `BrandAiUsageBudget` and repository now explicitly mark an uncertain provider attempt `UNCERTAIN` rather than mislabeling it `FAILED` or `SUCCEEDED`. `UNCERTAIN` counts against the UTC daily brand request limit; quota reports disclose its count. The new creative workflow and existing autonomous research/resource worker use this when a remote operation may have been charged.
- This is a **request-count safety measure**, not a billed dollar cap; actual provider invoicing remains unavailable. A failed request known not to have initiated external work may still use existing `FAILED` semantics in other services.

## New or changed source files

- New: `app/Services/Creative/{GeneratePrivateCreativePreview,DownloadPrivateCreativePreview}.php` and `app/Http/Controllers/Api/V1/DownloadPrivateCreativePreviewController.php`.
- New: `resources/js/Pages/Organisations/Brands/Content/PrivateCreativePreviewPanel.jsx`.
- New: `app/Http/Controllers/Api/V1/RevisePlannerAutonomousResourceController.php` and migration above.
- Updated: autonomy repository contract+Eloquent, delegation management, agent review panel, Content Studio index/controller, AI budget contract+Eloquent/service, autonomous worker, `routes/web.php`, `config/planner.php`, `config/services.php`, both `.env.*.example` files.
- Added: `tests/Unit/Creative/PrivateCreativeImageValidationTest.php`, `tests/Standalone/private_creative_preview_contract.php`, `tests/Frontend/private-creative-and-human-review.test.mjs`, and expanded integration tests for revision and uncertain quota accounting.

## Staging activation / acceptance

1. On a **disposable MySQL 8 database**, reconcile Composer dependencies and install the vendor packages, then review and apply the additive migration. Never run `migrate:fresh` against an existing organisation database. Verify rollback and foreign key safety.
2. Run `php artisan test --filter=PrivateCreativeImageValidation`, `php artisan test --filter=PlannerAutonomousResearchRun`, `php artisan test --filter=BrandAiUsageBudget`, and complete PHPunit/MySQL suites, including concurrent editing and revocation. Run `npm ci`, `node --test tests/Frontend/*.test.mjs`, `npm run build`, plus browser interaction and CSRF tests.
3. In staging, set `PLANNER_AI_API_KEY`, grant provider image access, set `PLANNER_AI_IMAGE_MODEL=gpt-image-2.5-flare` (or an approved supported model) and explicitly enable `PLANNER_CREATIVE_IMAGE_ENABLED=true`. Keep `PLANNER_EMERGENCY_PUBLISH_STOP=true` and all publisher/provider gates disabled. Verify the browser PNG, no-store headers, current role/brand permissions, quota exhaustion and timeout uncertainty.
4. Separately upload the downloaded PNG through the reviewed Tetranyble Storage workflow and confirm real scan/verification, SHA-256, media inspection and manual linking. No source change here should create an unsafe package media record. Do not advertise automatic image-to-Storage ingestion.
5. Test two editors attempting to revise the same resource hash, 12-revision ceiling, revoked delegation/membership, cross-tenant and cancelled/promoted runs, OAuth/MCP origin rules, and human content approval after promotion.
6. Review Pensuh.com legal/privacy/data-deletion public changes independently; **they are documented but not deployed by this archive**.

## Verification actually performed here

- Full source PHP syntax lint: **1374 / 1374 passed**.
- Full node frontend architecture/source suite: **220 / 223 passed**; the 3 failures are the pre-existing missing `@babel/parser` (dependency not installed) in membership/onboarding tests.
- New focused frontend tests: **5 / 5 passed**.
- Standalone PNG/metadata integrity assertions: **9 / 9 passed**.
- Repository architecture, Kinship permission catalogue, migration structure checks: **passed**.
- Vendor `pint` not run (`vendor/` absent); Composer is not available here. `npm run build`, PHPUnit, installed framework boot, MySQL migration/lease execution, real OpenAI image generation, real media upload, browser tests, and live APIs **not performed**.

## Five batch statuses — DO NOT MISREPRESENT

| Original batch | Last documented source estimate (Increment 63, not recalculated) | Acceptance |
|---|---:|---|
| 1. Publishing Engine | 98% | IN PROGRESS — NOT ACCEPTED |
| 2. Content Operations | 98% | IN PROGRESS — NOT ACCEPTED |
| 3. AI Marketing Intelligence | 90% | IN PROGRESS — NOT ACCEPTED |
| 4. Analytics & Automation | 99% | IN PROGRESS — NOT ACCEPTED |
| 5. Integrations & Production | 98% | IN PROGRESS — NOT ACCEPTED |

The old unweighted source estimate (~97%) covered the original scope **before the user requested autonomous resource and image generation**. Do not increase any percentage merely because this incremental source was written; separately estimate expanded scope after runtime validation. An autonomous image-to-canonical-Storage ingestion pipeline, full recurring campaign automation, provider access, and production compliance/acceptance remain open. No AI tool can approve or publish its own outputs.
