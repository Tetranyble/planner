# Phase 1 increment — Organisation & Brand Directory (2026-11-14)

## Source authority & scope

Based on the **latest Phase 0 archive** `Planner_Phase0_Repository_CI_2026-11-14.zip` (itself sourced from user-tested `Planner(1).zip`). Kept the Laravel 13 + React 19 + Inertia + shadcn setup, Kinship and Storage package references, original schema, and Phase 0 CI workflow. **No schema migration changed.** This is a **read-only, prerequisite-safe Phase 1 slice**; the Phase 0 MySQL/Kinship/Storage runtime gate remains OPEN.

## Implemented features

- `GET /api/v1/organisations`: `auth:sanctum`, bounded `per_page`, shows **only active organisations with an ACTIVE organisation membership** for the caller. This *membership-based discovery* intentionally does not require a selected workspace: users must be able to select a workspace before Kinship workspace-scoped permissions can resolve.
- `GET /api/v1/organisations/{organisation:uuid}/brands`: `auth:sanctum`, `organisation.context`, Kinship `brand.view`, **active organisation + brand memberships**, active organisations and brands; tenant-scoped query joins. Cross-tenant and revoked access denied.
- `GET /organisations` and `GET /organisations/{organisation:uuid}/brands`: Inertia equivalents behind `auth,verified` with the same service/repository authorization boundary and safe serialization; React pages with shadcn Button, accessible empty states, links and pagination.
- DI contracts and Eloquent implementations: `OrganisationDirectoryRepositoryContract` / `OrganisationDirectoryRepository`, `BrandDirectoryRepositoryContract` / `BrandDirectoryRepository`. Services `ListOrganisationsService` and `ListBrandsService` own input checking and actor authorization; repositories own query scoping. **Neither repository extends the unsafe unscoped BaseRepository**.
- Resources `OrganisationSummaryResource`, `BrandSummaryResource` and `DirectoryPage` use explicit output allowlists. No internal IDs, member roles, credentials or brand `settings` escape into API responses.

## Files (added)

- `app/Repository/Contracts/{OrganisationDirectoryRepositoryContract,BrandDirectoryRepositoryContract}.php`
- `app/Repository/Eloquent/{OrganisationDirectoryRepository,BrandDirectoryRepository}.php`
- `app/Services/Directory/{ListOrganisationsService,ListBrandsService,DirectoryPage}.php`
- `app/Http/Resources/Directory/{OrganisationSummaryResource,BrandSummaryResource}.php`
- `app/Http/Controllers/Api/V1/{OrganisationDirectoryController,BrandDirectoryController}.php`
- `app/Http/Controllers/Workspace/{OrganisationDirectoryController,BrandDirectoryController}.php`
- `routes/api.php`
- `resources/js/Pages/Organisations/{Index,Brands}.jsx`
- `tests/Unit/Services/Directory/{ListOrganisationsServiceTest,ListBrandsServiceTest}.php`
- `tests/Feature/Directory/DirectoryEndpointsTest.php`
- `tests/Integration/Repository/DirectoryRepositoryTest.php`

## Modified

- `bootstrap/app.php` registers versioned API routes.
- `routes/web.php` registers Inertia directory routes.
- `app/Providers/AppServiceProvider.php` adds explicit contract bindings.
- `resources/js/Layouts/AuthenticatedLayout.jsx` adds desktop/mobile directory navigation.
- `CONTINUE_HERE.md`, `docs/engineering/{09_HANDOFF_AND_PROGRESS.md,PHASE_TRACKER.csv,TEST_MATRIX.csv,DOCUMENT_MANIFEST.json}` track the slice.

## Security invariants

1. Never list organisations without `organisation_memberships.user_id = actor.id` and `state = ACTIVE`, and `organisations.status = ACTIVE`.
2. Brand listing requires `CurrentOrganisation::idFor(actor) === organisation.id`, Kinship `brand.view`, active `brand_memberships`, active `organisation_memberships`, and active brand and organisation statuses.
3. Route path uses organisation **UUID**, not exposing internal bigint identifiers.
4. Only explicit resource fields are serialized. Do not pass raw Eloquent models to Inertia props.
5. Controllers call services only; services call explicit repository contracts; DB joins live exclusively in the Eloquent repositories.
6. Client-provided `per_page` is validated to 1–100. Pagination and deterministic ordering use database queries.

## Tests & evidence

| Check | Exact result | Status |
|---|---|---|
| PHP syntax (`find app tests routes bootstrap -name '*.php' -print0 \| xargs -0 -n1 php -l`) | 272 files passed in this container | **PASS** |
| `python3 scripts/check_migration_structure.py` | 91 application-owned tables; dependency/order and static tenancy checks | **PASS (static only)** |
| `php scripts/check_repository_boundaries.php` | 4 Eloquent repositories pass | **PASS (static)** |
| `php scripts/verify_brand_scope.php` | 10 assertions | **PASS (standalone)** |
| Babel JSX parser (`@babel/parser` from available source dependencies) | Both new pages and existing updated layout parse | **PASS (syntax only)** |
| Standalone `ListOrganisationsService` assertions via installed vendor classes | Four assertions (delegation, bounds, unsaved actor, no rejected calls) | **PASS (standalone)** |
| `php artisan test --testsuite=Unit` | Not executed: local PHP lacks `dom`, `mbstring`, `xmlwriter`, `intl` | **BLOCKED** |
| `php artisan test --testsuite=Feature` | Not executed: same extensions and database missing | **BLOCKED** |
| `php artisan test --testsuite=Integration` | Not executed: no `pdo_mysql` / MySQL server in container | **BLOCKED** |
| `npm ci && npm run build` | Not executed in this container; CI remains configured to install Linux deps | **NOT RUN** |

**Important:** Auth/permission correctness is encoded and tested *in source*, but not claimed verified by runtime until green Feature/Integration tests exist. The new tests exercise unauthorized request, inactive/revoked membership, missing Kinship permission, cross-org visibility and safe API fields.

## CI command sequence for the next engineer

Run on a local/CI machine with required PHP extensions, working MySQL 8 and Composer locked dependencies:

```bash
cp .env.testing.example .env.testing  # configure disposable test DB credentials
php artisan key:generate --env=testing --force
php scripts/assert_safe_test_database.php
php artisan migrate:fresh --env=testing --force
php artisan migrate:rollback --env=testing --step=1 --force
php artisan migrate --env=testing --force
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
npm ci --ignore-scripts
npm run build
```

The CI workflow `.github/workflows/test.yml` contains the MySQL service and extension installer. Review test failures carefully before changing package APIs; prefer installed package source over assumptions.

## Next exact task

Finish **P00-02/03/04/07** runtime validation, and then continue Phase 1 with **organisation creation/owner bootstrap and Kinship role synchronization**. Avoid exposing create/mutate endpoints until unique ownership, permission seeding, transaction, idempotency and audit behavior have been proven in MySQL. After that, implement brand creation and membership grant/revocation, with feature + real MySQL integration tests. Preserve the repository pattern, tenant constraints, and existing React/shadcn components.
