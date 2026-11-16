# Phase 4 increment 32 — Content & Media Studio (2026-11-14)

## Baseline

`Planner_Phase3_Grant_Reconciliation_2026-11-14.zip`. This is the first Content & Media vertical slice. It does not finish the production publishing system.

## Implemented contracts

- `ContentStudioRepositoryContract` + `ContentStudioRepository`: all content, versions, account discovery and media lookups are bounded by **organisation_id AND brand_id**. `AppServiceProvider` binds the interface.
- `ManageContentStudio`: Kinship plus brand-membership authorization on every read/write, including non-HTTP callers; transactions lock organisation then brand, and the item for revision writes. Audited creations, revisions, asset links and draft targets.
- `ContentVersionPayload`: versioned, explicit schema (`schema_version=1`, `format=plain_text`, `copy_text` and ordered `media_uuids`); SHA-256 content hash. JSON only stores the tiny format descriptor, not opaque business rules.
- Immutable `ContentVersion` and `ContentTarget` Eloquent rows. Edits append new versions with optimistic `expected_version`; old versions and targets remain unchanged.
- Existing media upload is owned by `tetranyble/storage`. `StorageMediaInspector` only imports canonical media created by the acting user in the current organisation, not temporary, deleted, quarantined or unsafe media. A linked asset is re-inspected when attached to a new version. Other teams' Storage media must be shared through a future package-ACL adapter, not by relaxing these checks.
- Draft account variants for **all six supported providers**: Facebook, Instagram, LinkedIn, X, TikTok and YouTube. Account status must be ACTIVE. Their `publishing_mode` is always ASSISTED and **no publication jobs are created**.
- Kinship `model.action` edge middleware for `content_item`, `content_version`, `media_asset`, `content_target` on both API and web transports. No new special permissions; the contributor/manager role catalogue receives the missing target permissions.
- React/Inertia/shadcn screens using shared tokens, brand directory link, standard resource actions, and FormRequests.
- All eleven Content/Campaign/Media-related application factories filled with explicitly scoped `forBrand`, `forItem`, and `forVersionAnd...` states; opt-in deterministic demo seeder uses factories. Default production seeding does not create demo content.
- Two **additive** unique indexes prevent duplicate canonical media imports and duplicate account-specific target payloads. Review old records for duplicates before applying to an existing database.

## HTTP contracts

Under `/api/v1/organisations/{organisation:uuid}/brands/{brand:uuid}/content` and equivalent Inertia route:

- `GET /`: paginated draft list and permitted media/accounts.
- `GET /{itemUuid}`: item with immutable versions, media references and permitted draft targets.
- `POST /`: create draft and first version atomically (`title`, `content_kind`, `copy_text`, optional `media_uuids`, `campaign_uuid`, `series_uuid`).
- `POST /{itemUuid}/versions`: append with required `expected_version`, `copy_text`, and optional `media_uuids`; stale writers get HTTP 409.
- `POST /media-assets`: register an existing, safe Storage media ID belonging to this uploader and organisation. No duplicate filesystem / upload implementation.
- `POST /{itemUuid}/targets`: create idempotent, account-specific `ASSISTED`-only draft (version UUID, active social account UUID, platform caption).

## Security invariants and caution

- No cross-tenant/brand data lookup. `brand_id`, `organisation_id`, the item state and publishing mode are never accepted from client payloads.
- Content version media references must be trusted, current and attached at version creation; they cannot be changed later.
- A Storage record can become unsafe **after** a draft was created. The future publication engine MUST independently re-check provider credentials, active account capabilities, the canonical media safety/quarantine state, and full approval policy before making any external side effect.
- The platform target is *not* a publishing job. Do not present it as published, scheduled or eligible for publishing.
- We do not expose provider credentials or raw package object paths to browsers or AI tools.
- New ContentVersion and ContentTarget models reject updates/deletes; any future policy requiring edits must append a new row instead.
- `tetranyble/storage` own library/upload routes may be disabled (`STORAGE_ROUTES_ENABLED`); media upload UI is deliberately not reimplemented here. Linkage uses a canonical media ID; improve UX later through the package media picker and ACL API.
- `created_at` on version rows is a timestamp but there is no content `current_version_id` pointer; the source of truth is max version number, locked under item/brand transaction.

## Tests authored

1. Unit: deterministic, order-sensitive content hashes.
2. Feature/MySQL: create → append → stale writer 409 → immutable original; cross-organisation denial; invalid payload 422; foreign-brand media rejection; missing package media rejection; target idempotency.
3. Integration/MySQL: factory-backed repository tenant isolation and missing cross-brand resource.
4. Frontend/architecture: Kinship on both transports, controller conventions, scoped repository, immutable records, Storage ownership, factories/seeders.
5. CI: new `npm run test:content-studio` gate runs with the existing project matrix.

## Verification performed and unverified gates

PHP syntax, repository boundary, Kinship catalog, test-fixture rules, static migration structure, standalone content hash smoke and focused Node architecture tests were executed. JSX parsed successfully. **Full Laravel PHPUnit, MySQL FK/index execution, Tetranyble Storage upload/ACL integration, real Kinship middleware and full Vite production build were not executed in this environment.** They are NOT green until you run them.

Suggested local validation:

```bash
composer install
npm ci
php artisan migrate --env=testing --force
php artisan kinship:seed --help # review command options before using a real tenant
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
npm run test:content-studio
npm run build
```

## Next development slice

Implement a first-class Storage media picker/uploader using package ACL APIs (not direct disk access), rich source-supported content formats and document schemas, drafts with label/series management, and a formal approval request lifecycle that always pins specific immutable content target/version/media hashes. Followed by durable publication jobs, provider validators and API adapters under the existing authorisation/privacy rules.

## Separate Pensuh.com compliance task

The Pensuh.com Privacy, Cookies, Terms, data deletion instructions and related legal page updates remain tracked and **not deployed**. Do not conflate the Planner app privacy callback with publication of Pensuh marketing-site legal copy.
