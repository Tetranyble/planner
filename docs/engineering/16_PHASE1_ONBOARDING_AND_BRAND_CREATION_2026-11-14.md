# Planner Phase 1 — Organisation Owner Onboarding + Brand Creation

**Snapshot:** 2026-11-14 | **Source baseline:** `Planner_Theme_Tokens_Light_Dark_2026-11-14.zip` | **Status:** IMPLEMENTED_UNVERIFIED (MySQL / PHPunit / CI pending)

## Scope actually implemented

This increment adds the **creation** side of Phase 1; it does **not** implement general member invitations, brand editing, role management screens, or any social-provider integration.

- `POST /api/v1/organisations` and `GET /organisations/create`, `POST /organisations`: authenticated, email-verified owner onboarding.
- `POST /api/v1/organisations/{organisation:uuid}/brands` and `GET /organisations/{organisation:uuid}/brands/create`, `POST .../brands`: tenant-scoped brand creation.
- New React/Inertia creation forms use semantic shadcn/Tailwind tokens. Existing directory screens now offer creation links.
- Server-side request validation for brand name, URL, and time zone. HTTP cannot supply `organisation_id`, `created_by_user_id`, `role_id`, `status` or grants to the creation services.
- `User` now implements Laravel's `MustVerifyEmail` contract: previously present `verified` middleware had no effect for this model. This is an intentional authentication behavior change requiring staging verification.
- No migration changed. The schema continues to use integer primary keys and UUID route keys.

## Repository contracts / code inventory

| Component | File |
|---|---|
| Organisation writes | `app/Repository/Contracts/OrganisationWriteRepositoryContract.php`, `app/Repository/Eloquent/OrganisationWriteRepository.php` |
| Brand writes | `app/Repository/Contracts/BrandWriteRepositoryContract.php`, `app/Repository/Eloquent/BrandWriteRepository.php` |
| Audit writes | `app/Repository/Contracts/AuditEventRepositoryContract.php`, `app/Repository/Eloquent/AuditEventRepository.php` |
| Transactional orchestration | `app/Services/Onboarding/CreateOrganisationService.php`, `CreateBrandService.php` |
| Validation | `app/Http/Requests/Workspace/CreateOrganisationRequest.php`, `CreateBrandRequest.php` |
| REST controllers | `app/Http/Controllers/Api/V1/CreateOrganisationController.php`, `CreateBrandController.php` |
| Inertia controllers | `app/Http/Controllers/Workspace/OrganisationOnboardingController.php`, `BrandOnboardingController.php` |
| UI | `resources/js/Pages/Organisations/Create.jsx`, `Organisations/Brands/Create.jsx` |
| Existing screens updated | `resources/js/Pages/Organisations/Index.jsx`, `Organisations/Brands.jsx` |
| Composition and routing | `app/Providers/AppServiceProvider.php`, `routes/api.php`, `routes/web.php` |

`OrganisationWriteRepository` owns creation of organisation rows and defaults. `BrandWriteRepository` owns all brand persistence plus creator membership and uses the **actual** active organisation membership role under lock. The two services coordinate transactions, explicit authorization and append-only audit events. Do not allow HTTP controllers, UTCP tools or the future MCP adapter to bypass the services.

## Organisation creation transaction

1. `auth:sanctum` or `auth`, `verified` and request validation establish the caller.
2. Service requires a persisted authenticated User. New Organisation has `created_by_user_id = actor.id` and active status. No client-supplied role or tenant identifiers are accepted.
3. Create one `organisation_preferences` row with model defaults.
4. Invoke installed Kinship `PermissionCatalogSeeder::seed($organisation->id, workspaceScoped: true)`; **do not invent role tables or duplicate package migrations**.
5. Load the workspace-bound `owner` role. Invoke the existing `OrganisationMembershipService::grant` bootstrap path; it ensures the first membership belongs to the actual creator and assigns the Kinship grant under validated `CurrentOrganisation` context.
6. Record `organisation.created` within the transaction.
7. Commit. On an exception, organisation, preferences and membership rolls back. The Kinship cache/batch side effects require real integration testing after rollback.

## Brand creation transaction

1. Middleware validates actor's active membership to the route's `organisation:uuid`. Service additionally requires active org and Kinship `brand.manage` permission.
2. Lock the actor's active organisation membership row and read *its* role ID (never a client-provided role ID). Recheck authorization under lock.
3. Create brand scoped to the route organisation; default timezone is inherited from the organisation.
4. Create the creator's brand membership with the same organisation role ID, matching the existing composite foreign keys.
5. Record `brand.created` in the same transaction. Commit or roll back together.

This supports multiple brands per organisation and prevents one workspace's role/member being attached to a different organisation. **Brand membership remains separate from Kinship organisation-scoped permissions.**

## Security and intentionally open decisions

- `verified` middleware now has meaning because `User implements MustVerifyEmail`. API calls continue returning HTTP 409 for unverified users; browser requests redirect to the established `verification.notice` Inertia page. Validate registration, verification notification, reset and login flows before rollout.
- Per-user rate limiting added to creation routes (5 org/minute, 10 brands/minute). Consider additionally limiting outstanding organisations and implementing business-transaction idempotency keys for retries; current endpoints are **not** safely replay-idempotent.
- Existing `CurrentOrganisation` and Kinship behavior depends on package runtimes; do not certify permission caching/switching until MySQL tests pass.
- The first-owner bootstrap requires successful Kinship catalog seeding. Do not swallow seeding exceptions and grant a dummy owner role.
- Audit writes contain IDs, actor and action only; no provider tokens or personal marketing contact details.
- `PermissionCatalogSeeder` may trigger cache invalidation inside the transaction. Exercise DB rollback/cache consistency in a real package integration environment.
- No change was made to Tetranyble Storage media ownership or Planner's theme token values.
- In production, account creation should have app-level abuse controls (IP + account quota, optional anti-bot) beyond the per-route throttles.

## Tests authored (PHP, not executed here)

- `tests/Unit/Services/Onboarding/OnboardingGuardsTest.php` (2): reject unsaved actors and brand creation before persistence.
- `tests/Feature/Onboarding/OnboardingEndpointsTest.php` (10): authentication, email verification, validation, owner role/prefs/audit, transactional rollback on disabled Kinship catalog, spoofing prevention, cross-org denial, missing brand.manage permission, input rejection, and Inertia creation page restrictions. *Count by test methods in source; do not infer execution from authorship.*
- `tests/Integration/Onboarding/BrandWriteRepositoryTest.php` (2): wrong-workspace role and revoked membership are rejected at repository boundary.
- `tests/Frontend/onboarding-pages.test.mjs` (3): JSX parse and static interaction/accessibility contract. These are **not browser E2E tests**.

## Executed test/validation evidence

- `npm run test:theme` -> **PASS, 10/10**.
- `npm run test:onboarding-ui` -> **PASS, 3/3**.
- Babel parser over all `resources/js/**/*.jsx` -> **PASS, 34 files**.
- `find app tests routes scripts database/migrations -name '*.php' -print0 | xargs -0 -n1 php -l` -> **PASS, 382 files**.
- `php scripts/check_repository_boundaries.php` -> **PASS, 7 Eloquent repositories**.
- `php scripts/verify_brand_scope.php` -> **PASS, 10 assertions**.
- `node node_modules/vite/bin/vite.js build` -> **BLOCKED**, missing Linux Rolldown binding from provided Node bundle (macOS-native); `npm ci` on Linux and Vite build required.
- **NOT RUN:** `php artisan test` / `migrate:fresh` / `migrate:rollback` / real MySQL package tests; current container lacks PHP extensions (`mbstring`, `dom`, `xmlwriter`, `pdo_mysql`) and a MySQL service.

## Exact integration commands (disposable MySQL)

```bash
composer install --no-interaction --prefer-dist
npm ci
cp .env.testing.example .env.testing  # replace only local test credentials
php artisan key:generate --env=testing --force
php scripts/assert_safe_test_database.php
php artisan migrate:fresh --env=testing --force
php artisan migrate:rollback --env=testing --step=1 --force
php artisan migrate --env=testing --force
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
npm run test:theme
npm run test:onboarding-ui
npm run build
```

If the MySQL suite fails, fix it before adding further write APIs. Do not change production data with `migrate:fresh`. Verify Kinship initial owner seeding twice across distinct new organisations and confirm one cannot see the other's grants.

## Next milestone

1. **Gate P00:** run full MySQL/Composer/Storage/Kinship CI and resolve real failures, including old skipped tests and email verification behavior.
2. **Phase P01 remaining:** member invite/accept/revoke lifecycle; last-owner protection; brand membership and role assignment; cross-tenant tests, shadcn UI for members; audit authorizations.
3. **P02:** normalized brand profiles, audiences, voice, claim evidence, policy versioning; keep repository pattern.
4. Postpone social connectors and AI tool writes until authorization and idempotency gates are verified.

**Handoff:** Start with `CONTINUE_HERE.md`, this file, `PHASE_TRACKER.csv`, and `docs/engineering/02_REPOSITORY_PATTERN.md` in the updated archive. No Git SHA is available in this ZIP-based handoff.
