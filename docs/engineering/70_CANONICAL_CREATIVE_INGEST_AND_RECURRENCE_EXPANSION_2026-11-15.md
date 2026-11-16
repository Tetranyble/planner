# Increment 70 — canonical generated-image ingestion and recurring-campaign expansion

**Baseline:** Increment 69. **Delivery:** source-level integration candidate, not runtime / production accepted. **Date:** 2026-11-15.

## 1. Real package-owned creative image upload, not a surrogate uploader

The optional `auto_store` parameter on the **existing first-party creative preview endpoint** permits a human operator to trigger generation and pass the resulting private PNG straight into Tetranyble Storage v3's documented `UploadMedia::uploadLibraryFiles()` use case, using `LaravelIncomingFile::fromUploadedFile()`. The adaptation uses a short-lived 0600 staging file only; it does not write permanent media objects, invent Storage database records, install an alternative media provider, or bypass the package's quota/ACL/virus-scanning/processing rules. This API was verified against the package v3.0.0 published source (`https://github.com/Tetranyble/storage/blob/v3.0.0/src/Modules/Media/Application/UploadMedia.php`).

The creative generation flow still reserves and settles its daily provider allowance, checks first-party session and organisation/brand roles, and issues the expiring original-byte SHA-256 claim. The new adapter *also* checks the role, Kinship permission, brand, generated digest, PNG MIME and dimensions before the package API is invoked. `auto_store` is rejected **before a billable provider call** if either independent release gate is missing. A Storage failure after successful provider billing yields the original private PNG and an explicit failure header rather than automatically repeating the expensive request. No raw image bytes or API response are stored in Planner application tables.

Response headers distinguish `NOT_REQUESTED`, `PENDING_STORAGE_REVIEW`, and `FAILED`; a pending response has the canonical media ID. The operator interface can populate the ID automatically but must still visibly inspect the PNG and **explicitly** confirm review. The existing handoff finalizer re-reads the Storage record in the same user and workspace, demands CLEAN scan/approved final state, exact immutable SHA-256/byte count/dimensions/MIME, links via `ManageContentStudio::linkMedia()`, and persists AI provenance. An uploaded PNG is **never automatically a Planner MediaAsset**, never approved for publication, and never silently assigned to a draft.

- `PLANNER_CREATIVE_IMAGE_ENABLED=false` (existing)
- `PLANNER_CREATIVE_AUTO_STORAGE_ENABLED=false` (**new**)
- `PLANNER_STORAGE_V3_ADAPTER_VERIFIED=false` (existing)

**Scope boundary:** This is operator-triggered *automatic canonical ingestion*, **not autonomous agent-triggered image generation**. Agent-generated media remains an optional future product expansion that would need a durable uncertain-cost stage and human image review. No agent tool was given the right to generate or approve images.

## 2. Safe unattended expansion of recurring campaigns

`planner:expand-recurring-campaigns --limit=25` runs daily when `PLANNER_RECURRING_EXPANSION_ENABLED=true` (default **false**). It uses the exact DST-safe recurrence calculator introduced in Increment 69 and the new `recurring_campaign_occurrences` table. It projects a bounded rolling 30-day window, with a per-template 180-occurrence maximum, exact UTC / wall-clock identities, and deterministic unique keys. Lock order is organisation -> brand -> schedule. `next_expand_at` becomes the idempotent daily cursor, and the SQL uniqueness constraint prevents duplicate suggestions on replay.

`GET .../recurring-campaigns/{id}/occurrences` shows brand-scoped, readable projections in the existing operator desk. A projection has no approval, no publication intent, no queued provider action and no user delegation. The only code that marks `held_at` is the existing human-confirmed Hold path **after** `ManagePublicationIntents::create()` verifies an independent up-to-date approval and returns `AWAITING_USER`. Paused/retired templates are not expanded; previously held plans remain governed by the existing cancellation/reconciliation controls.

**Scope boundary:** No unattended `hold`, `arm`, `publish` or creative-generation call was introduced. External provider publication switches remain OFF. The projection worker is not a marketing autopublisher.

## 3. Verification actually completed in this environment

- PHP syntax: all source files parse (no Composer application boot, package contract runtime, or MySQL DDL execution).
- New static checks in `tests/Frontend/creative-upload-and-recurring-worker.test.mjs`: **3/3 pass**.
- Existing recurrence pure PHP fixture: **16/16 pass**.
- Migration structure check: **146 application-owned tables PASS (static)**.
- Repository and Kinship checks: **PASS** (57 repositories, 139 models).
- Node source/architecture suite: **232/235 pass**; the 3 unchanged test import failures require `@babel/parser` from `npm ci`. `package-lock.json` already includes it; do not suppress the tests or copy an unversioned parser.
- MySQL integration + first-party feature test code added for the new worker/auto-upload gate; **authored but not executed** because the uploaded repository lacks Composer vendor packages, pdo_mysql and full extension stack. Neither `npm run build` nor full browser E2E ran.

The public package API is documented, but **its exact installed runtime contract remains unverified**. Confirm package version and filesystem behavior on the target machine; don't enable `PLANNER_STORAGE_V3_ADAPTER_VERIFIED` based on README/source inspection alone. External OpenAI/image-provider and Storage processing integration has not been tested here.

**Dependency blocker:** `composer.json` declares `laravel/passport:^13.0` and `laravel/mcp:^1.2` which are not present in `composer.lock`. This was inherited from earlier increments. Reconcile via Composer on the user's PHP 8.4 machine (review solver result and lock diff) before attempting application boot. `package-lock.json` correctly includes `@babel/parser`, but npm dependencies weren't installed in this environment.

## 4. Local acceptance plan (user-owned environment)

```bash
# First reconcile declared packages against composer.lock and inspect the resulting diff.
composer update laravel/passport laravel/mcp --with-all-dependencies
composer validate --no-check-publish
composer install
npm ci
# Point .env.testing at a DISPOSABLE MySQL 8 schema only.
php artisan migrate:fresh --env=testing --force
php artisan test --filter=RecurringCampaignExpansionPersistenceTest
php artisan test --filter=CanonicalCreativeAutoUploadGateTest
php artisan test --filter=RecurringCampaignEndpointsTest
php artisan test --filter=CreativeImageHandoffEndpointsTest
node --test tests/Frontend/*.test.mjs
npm run build
php artisan planner:audit-production-release --strict --json
```

Staging probes, **before** setting the upload gate true: (a) verify v3 composer class signatures; (b) `PLANNER_STORAGE_V3_ADAPTER_VERIFIED=true` only after a real upload + malware scan + storage ACL test; (c) generate with `auto_store=true`, confirm only a private Storage ID, inspect a quarantined/pending object cannot link; (d) finish scan and link after human review, check durable `GENERATED` attribution, negative cross-brand and stale claim paths, and no social calls; (e) enable the review-only expansion gate, run two concurrent worker processes, verify exact-once keys, pause/resume/retire and distinct-tenant isolation; (f) test DST fold/gap; (g) disable all integration flags and ensure emergency publishing stop remains enabled.

## 5. Five-batch completion / privacy disclosure

This closes the two previously named source-path gaps (canonical image import and background recurrence **projections**). It does **not** certify complete autonomous creative agents, provider sandbox acceptance, the installed Storage contract, Passport OAuth handshake, production secrets, or live release readiness.

| Original batch | Latest prior estimate | This increment | Formal status |
|---|---:|---|---|
| Publishing Engine | 98% | Source candidate, runtime unverified | IN PROGRESS / NOT ACCEPTED |
| Content Operations | 99% | Source candidate, runtime unverified | IN PROGRESS / NOT ACCEPTED |
| AI Marketing Intelligence | 95% | Operator image ingestion added; agent image-generation stage not implemented | IN PROGRESS / NOT ACCEPTED |
| Analytics & Automation | 99% | Review-only recurrence expansion implemented | IN PROGRESS / NOT ACCEPTED |
| Integrations & Production | 98% | Package upload API integrated in source; Composer and live safety unverified | IN PROGRESS / NOT ACCEPTED |

The prior rounded **~98% source estimate** cannot be mechanically promoted to 100% from static checks. Treat Increment 70 as the **remaining-path source handoff candidate**, not a production-ready release; **0 of 5 batches is runtime accepted**.

The separate Pensuh.com privacy/cookies/data-deletion workstream is specified under `docs/compliance/PENSUH_COM_PRIVACY_AND_LEGAL_PAGES_WORKSTREAM.md`. Its actual Next.js repository is not included here, so **no Pensuh.com pages were modified or deployed**. Public policy text, contact details, and retention periods must be grounded in the Pensuh application and reviewed before deployment. Don't claim those obligations are complete based on a Planner ZIP.
