# Phase 1 — Brand membership lifecycle (implemented, runtime-unverified)

**Authority:** This project snapshot builds on `Planner_Phase1_Organisation_Memberships_2026-11-14.zip`. The established `app/Repository/Contracts` + `app/Repository/Eloquent` pattern is mandatory; Kinship remains the sole owner of role/permission tables and role grants. No duplicate authorization library has been implemented.

## New implementation

- `BrandMemberRepositoryContract` and `BrandMemberRepository` encapsulate active organisation/brand membership queries, role lookups, locks, pagination, candidates and writes.
- `ManageBrandMembers` is the transactional application service, shared by web/API and suitable for future UTCP/MCP handlers.
- `BrandPermissionGuard` validates active organisation context, Kinship permission, active brand membership, and the brand role's effective permission. Organisation owners can recover brand membership access only for brand-membership operations.
- `BrandRoleHierarchy` prevents granting a brand role stronger than the target user's organisation role. Owner changes are limited to an organisation owner. Mutations record audit events.
- Web `/organisations/{organisation:uuid}/brands/{brand:uuid}/members` and REST `/api/v1/organisations/{organisation:uuid}/brands/{brand:uuid}/members` implement index, add, role change, revoke. Routes use Kinship's native `kinship.role` and `kinship.permission` middleware after organisation-context selection. The service repeats authorization for non-HTTP callers.
- Inertia/React membership screen uses the existing shadcn/theme tokens and preserves validation/pending states.

## Test data conventions

`database/factories/BrandMembershipFactory.php`, `OrganisationMembershipFactory.php` and `OrganisationInvitationFactory.php` provide domain-specific record builders with explicit organisation, user and role selection. `PlannerDevelopmentSeeder` is **opt-in** and prohibited in production. `OrganisationSeeder` and `BrandSeeder` seed an example workspace by invoking real onboarding/membership services and are designed for safe local repeat execution. They do not seed real credentials or school/contact data.

**Do not use `DB::table()->insert()` in test setup when a model factory or application service exists.** Assertions like `assertDatabaseHas` are appropriate. Application repository implementations are separate from fixture factories. Do not manufacture unrelated production data merely to fill empty generated seeder classes.

## Required runtime checks (not run in this environment)

1. `php artisan migrate:fresh --env=testing` on disposable MySQL 8, then rollback and migrate again.
2. `php artisan test --testsuite=Unit`, `--testsuite=Feature`, `--testsuite=Integration`.
3. Verify manager + viewer-brand membership denies administration, owner recovery after brand revocation, role escalation denial and brand/organisation mismatch rejection.
4. Repeat catalog synchronization and ensure role grants reflect the configured `.` separator, not legacy `_` strings.
5. Run Linux Vite build and browser tests; local bundled macOS native dependency cannot be used to certify Linux builds.

**Status:** Implementation source and tests authored; no production verification claimed.
