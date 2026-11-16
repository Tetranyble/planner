# Planner — implementation fixes (14 November 2026)

## Deliverable

This source snapshot incorporates the migration and package-integration changes described in the prior audit. The original uploaded project was left untouched. **This is a source patch, not a claim of deployed or runtime-tested functionality.** The supplied `composer.lock` pins `tetranyble/kinship` v2.0.1 and `tetranyble/storage` v3.0.0.

## Changes applied

1. **Kinship workspace:** `config/kinship.php` now points to `App\Models\Organisation`, enables a custom resolver, disables automatic model permission discovery, and defines a marketing-only permission catalogue. The package continues owning `roles`, `permissions`, grants and groups; package migration loading stays enabled.
2. **Verified context:** Added `CurrentOrganisation` (request-scoped), `CurrentOrganisationKinshipResolver`, and `ResolveOrganisationContext` middleware. An organisation ID is accepted only if the user has an active membership and the organisation is active. A header/route parameter alone never grants access. Use `auth` then `organisation.context` then `kinship.permission` on protected routes. No business routes were added in this patch.
3. **Host models:** `Organisation` implements Kinship's Workspace contract. `User` implements Kinship WorkspaceSubject and StorageUser, using the corresponding package traits. Storage points to host `users`/`organisations` tables and remains route-disabled by default. The Storage workspace accessor is derived from the validated current-organisation context rather than a persisted `users.workspace_id` column.
4. **Membership flows:** `OrganisationMembershipService` checks role workspace, requires an authenticated actor and an appropriate Kinship permission for existing organisations, and synchronizes active organisation membership roles. Initial organisation-owner provisioning is limited to the organisation's recorded `created_by_user_id`. Revocation clears scoped Kinship grants first. `BrandMembershipService` checks organisation membership and role scope without granting organisation-wide privileges merely from brand membership. `BrandAuthorizer` requires Kinship permission **and** brand membership.
5. **Schema isolation:** Removed the incorrect organisation/user uniqueness constraint from `brand_memberships`; added an FK requiring matching organisation membership. Added composite organisation+brand FKs and corresponding composite keys across brand-scoped tables, and scoped child-parent FKs where source and target keys were present and the parent migration was already executed. These prevent mismatched organisation/brand IDs at the database level.
6. **Geography:** Audience state and city references now validate their country/state hierarchy using composite FKs and parent unique indexes.
7. **Migration defects:** Fixed two rollback table names, the outreach computed marker, the publishing due-time index and the incorrect approval `nullable(...)` modifier.
8. **UUID:** Applied the existing `Uuidable` trait to previously unprotected models with required UUID columns.
9. **Storage ownership:** Added nullable `media_assets.storage_media_id` with an index and made old physical-media snapshots nullable. The storage package remains the intended source of truth for original bytes and derivatives. **No speculative FK is added to Storage's table because its installed migration order and model class must be validated in Composer/MySQL.** New media services must enforce a valid Storage media reference before considering a marketing asset publishable.
10. **Enum typo:** Corrected `SUSPENNDED` to `SUSPENDED` in the host enums, with a forward-only data correction migration for prior records.
11. **Tests and tooling:** Added focused integration tests for context, tenancy, package configuration and UUIDs; enabled the formerly skipped multi-brand membership test; adjusted its fixture setup; added `scripts/check_migration_structure.py`.

## Critical runtime checks still required

- Run **actual MySQL 8.x migrations** and rollbacks on a disposable test database. PHP lint and a static dependency check cannot prove SQL DDL succeeds. Package migrations must create Kinship roles before the application references `roles.id` and Storage tables before media linking is finalized.
- Verify the exact `StorageUser` contract/`BelongsToStorageWorkspace` behavior in the installed v3.0.0 vendor source. The current user model provides a computed workspace ID; validate that `AuthenticatedWorkspace` correctly resolves this for every package operation and that direct HTTP routes remain disabled until access-control tests pass.
- Confirm Kinship's `syncRoles()` accepts a Role model, zero-role revocation and the workspace resolver in v2.0.1. Test cache isolation during tenant switches and denied access after membership revocation.
- Run tests added in `tests/Feature/Platform/` and existing tests with MySQL. Some old test fixtures relied on application-unscoped Kinship roles and may need modernization (tests that assume a soft-deleted role should violate a FK are deliberately still skipped).
- Test social/media provider functionality, queue retries and unique-index performance using a real database. Neither provider credentials nor a live remote publishing integration were configured.

## Installation/runbook

```bash
composer validate --strict
composer install
cp .env.example .env
php artisan key:generate
# Set DB_* in .env to a NEW DISPOSABLE MySQL test database.
php artisan migrate:fresh --database=mysql --env=testing
python3 scripts/check_migration_structure.py
php artisan test --filter=OrganisationContextTest
php artisan test --filter=TenantForeignKeysTest
php artisan test --filter=HostPackageConfigurationTest
php artisan test --filter=BrandMembershipTest
php artisan test
php artisan kinship:seed --dry-run --workspace=<organisation-id> --guard=web
php artisan storage:health --strict
```

Do not run `migrate:fresh` against a non-disposable database. Laravel migrations were edited in place to support an early-stage *fresh schema*. For an existing database with data, build **separate additive ALTER/backfill migrations after verifying rows and constraints**; simply copying this source does not retrofit its new FKs into tables already created. Review the added `organisations.created_by_user_id` column during onboarding.

## Application development conventions

- Resolve context at request entry after authentication, never from an unverified organization header or prompt.
- Never grant cross-brand access from an organisation role alone; call `BrandAuthorizer` with the requested permission and explicit Brand.
- Create membership roles via Kinship scoped to the organisation; do not save arbitrary global Kinship role IDs into membership records. Use dedicated membership services rather than inserting pivots directly in controllers.
- Store canonical bytes, derivatives, quota and retention in Tetranyble Storage. `media_assets` stores only campaign/brand metadata and a reference to the package media record (with legacy snapshots until migration).
- Use only the appropriate marketing permission identifiers from `config/kinship.php`, and seed catalogue explicitly per organisation.
- MCP/UTCP tool handlers must reuse these same services and approval gates. This patch does not deploy a working MCP or UTCP endpoint.
