# Increment 68 — Reviewed creative PNG → canonical Storage handoff

**Baseline:** Increment 67. **Date:** 2026-11-15. **Status:** SOURCE IMPLEMENTED; **not** MySQL, package, provider or production-accepted. This increment targets **~98% aggregate estimated original source scope**, not 98% verified readiness.

## Implemented

1. **Short-lived, operator-bound generated-image handoff.** The existing OpenAI PNG preview now registers ONLY a SHA-256, byte length, dimensions, media type and `(organisation_id, brand_id, actor_user_id)` claim. No images/base64, provider secrets, raw response, or publicly exposed file URL enters the handoff database. `creative_image_handoffs` is an additive table with composite tenant foreign keys, a one-time UUID, state, expiry and a canonical asset reference. The preview's private `no-store` response includes its claim and expiry headers. TTL is `PLANNER_CREATIVE_HANDOFF_TTL_MINUTES=60`, bounded to 5–120 minutes; creative generation remains disabled by default.
2. **First-party reviewed linking:** `POST .../creative/handoffs/{handoffUuid}/link` requires authenticated verified session, first-party/CSRF middleware, `media_asset.store` at the route and service edges, an accepted human-review assertion, and a valid owner-scoped unexpired claim. Inside a tenant-locked transaction, the Tetranyble Storage inspector demands a canonical, non-quarantined, scanned, actor-owned, finished file. The file must match the generated PNG's exact SHA-256, size, MIME and dimensions. Normal `ManageContentStudio::linkMedia()` creates/reuses the canonical asset with its own permission check and audit. The handoff is consumed once; repeat claims for the *same* media are idempotent and re-inspect current safety. Trying a different file gives a conflict. No publication, approval, target, or outbound provider request is triggered.
3. **Canonical verified upload browser:** The existing package-owned candidate endpoint now returns the SHA-256 and dimensions for verified actor uploads. Creative Studio filters these to matching PNGs, so the operator can select an already-approved Storage media ID instead of guessing. It also permits manual ID after the package upload. No independent uploader, direct filesystem writer or speculative Storage package endpoint was added.
4. **Immutable-draft AI provenance:** Linked creative assets carry durable `MediaSourceType::GENERATED` provenance; linked handoff rows additionally act as evidence. `ManageContentStudio` refuses to create or append a version containing a known generated asset when `ai_generated` is `false` or `null`. Source-screen media carries an AI badge and selecting one preselects `ai_generated=true`. This disclosure is incorporated into existing version hashing and approval policy; AI never self-approves. The source marker remains after claim pruning.
5. **Metadata retention:** `planner:prune-creative-handoffs` has a dry-run mode, bounded batch and scheduled daily run. Removes only **expired pending claims older than seven days** and **consumed claim metadata older than 90 days**. It does not delete audit logs, durable `media_assets.source_type`, media objects, versions, approvals or posts. Manual verification of deployment retention and package ownership remains necessary.

## Important limitations

- This is an **integrity-checked operator upload handoff**, not direct autonomous AI image-to-Storage ingestion. The operator must download and review the image, upload the original bytes through Tetranyble Storage, await scanning and then link it. Automatic provider-to-Storage binary upload depends on validating the installed `tetranyble/storage` uploader API, ACLs and workspaces in the actual Composer/runtime environment. Never bypass its trust pipeline.
- A positive human-review checkbox records attestation; it is not an automated quality, copyright or brand-claim certification.
- `composer.lock` and `package-lock.json` remain unchanged. Composer packages, Laravel/PHPUnit, MySQL 8, real Tetranyble upload/scan, browser/Vite, OpenAI image API, MCP/OAuth and all social provider sandboxes remain unverified. New source-side tests do **not** establish live safety.
- `PLANNER_CREATIVE_IMAGE_ENABLED=false` and all production posting switches stay disabled. Pensuh.com privacy/data-deletion public page deployment remains separate and outstanding.

## Tests actually executed

| Test | Result |
|---|---|
| PHP syntax lint | 1,417 / 1,417 PASS |
| Source/frontend architecture suite | 225 PASS / 228 TOTAL; 3 baseline tests cannot import `@babel/parser` here |
| New creative handoff focused architecture tests | 5 / 5 PASS |
| Standalone `creative_handoff_fingerprint.php` | 13 / 13 assertions PASS |
| Existing standalone private PNG validation | 9 / 9 PASS |
| `scripts/check_repository_boundaries.php` | PASS — 56 Eloquent repositories |
| `scripts/check_kinship_catalog.php` | PASS — 138 models, 998 discovered+special permissions, 4 roles |
| `scripts/check_migration_structure.py` | PASS — 145 owned tables, composite brand FK; **static only** |
| New PHPUnit Unit, Feature, Integration tests | **Authored but NOT EXECUTED** (Composer/vendor missing) |
| Executed MySQL FK checks, application migration, browser, external APIs | **NOT EXECUTED** |

## Reproduce on a disposable staging database

```bash
# Review both lockfiles; resolve laravel/mcp lock mismatch explicitly before install.
composer validate --no-check-publish
composer install
npm ci
php artisan migrate --env=testing --force  # disposable MySQL 8 ONLY
php artisan test --filter=CreativeImageFingerprint
php artisan test --filter=CreativeImageHandoff
php artisan test --filter=ContentStudio
node --test tests/Frontend/*.test.mjs
npm run build
php artisan planner:prune-creative-handoffs --dry-run
```

Exercise generated preview → malicious/mismatched upload → verified exact-PNG import → duplicate link → expired/revoked account → cross-brand claim → draft `ai_generated` false/true → independent approval. Include two operators and separate brands and tenants; use real Storage scanning, then test linked media becoming quarantined. Do not enable publishing before provider receipt validation.

## Five batches — provisional original-source estimate (not acceptance)

| Batch | Increment 67 last documented | Increment 68 estimated | Status |
|---|---:|---:|---|
| 1. Publishing Engine | 98% | **98%** | IN PROGRESS / NOT ACCEPTED |
| 2. Content Operations | 98% | **99%** | IN PROGRESS / NOT ACCEPTED |
| 3. AI Marketing Intelligence | 90% | **95%** | IN PROGRESS / NOT ACCEPTED |
| 4. Analytics & Automation | 99% | **99%** | IN PROGRESS / NOT ACCEPTED |
| 5. Integrations & Production | 98% | **98%** | IN PROGRESS / NOT ACCEPTED |
| **Unweighted original-scope source average** | **~96.6%** | **97.8% (≈ 98%)** | **0 / 5 batches accepted** |

The estimated gain is justified by an end-to-end **source** handoff lifecycle, persistent AI origin, content disclosure, first-party guard and targeted test coverage. It is still a judgement, not a measured feature inventory. It does NOT mean the full expanded autonomous creative-ingestion vision is 98% done or that the project is release-ready. The remaining source work is canonical Storage-owned automated ingestion (only after package contract inspection), optional safe draft auto-association and full recurring campaign coverage; actual dependencies, MySQL, provider and deployment runtime acceptance remain substantial open gates.
