# ADR — Kinship model discovery, special permissions and route-edge authorization

**Status:** Accepted as the authoritative permission strategy for Planner. **Verification:** Static catalogue guard and PHP lint passed; full Laravel/real MySQL runtime tests have not executed in this environment.

## Source of truth

Use `config/kinship.php`, `Tetranyble\Kinship\Catalog\ConfigPermissionCatalog`, and `kinship:seed`. Do not create another permissions table, RBAC service, separate role seeder, or custom middleware to duplicate Kinship. Only application-specific brand/tenant checks belong to Planner.

- `catalog.enabled=true`, `catalog.discovery.enabled=true`, `catalog.discovery.path='Models'`, `catalog.separator='.'`.
- Package discovery scans `App\Models`, derives `Str::snake(class_basename())` as the resource, and combines it with each configured ability. Example: `BrandMembership` + `store` → **`brand_membership.store`**; `Role` is package-owned so it is listed explicitly in `catalog.resources`, along with `Permission` and `Group`.
- `catalog.abilities`: `index`, `view`, `store`, `update`, `delete`, `restore`, `force_delete`. We intentionally use Laravel `store`, **not** `create`, for POST persistence. These must match real route action semantics.
- Custom capabilities only when CRUD cannot describe the operation: `content.approve`, `publication.schedule`, `publication.cancel`, `publication.retry`, `social-account.connect`, `social-account.disconnect`, `integration.manage`, `automation.execute`, `ai-run.execute`, `ai-run.approve-action`, `lead.export`, `analytics.export`.
- `viewer`, `contributor`, `manager`, `owner` are the only predefined role names. `BrandRoleHierarchy` currently supports precisely these four roles. New default roles require an explicit hierarchy/policy change and tests before use.
- Role permission lists intentionally allowlist sensitive resources. Avoid blanket `*.index` and `*.view`: Kinship discovers private models like API logs, provider connections, audit logs and user data. Owner grants `*`; only owner has organisation membership administration, integration secrets and raw data export privileges.

## Authorization order

1. `auth` + `verified` as required.
2. `organisation.context`: validate real active organisation membership and select workspace for Kinship.
3. `kinship.role:owner,manager` (for administrative brand workflows) and `kinship.permission:brand_membership.store` (per action). For organisation member administration require `kinship.role:owner` and the corresponding `organisation_membership.*` or `organisation_invitation.*` permission.
4. Service authorizes again using `CurrentOrganisation` and `BrandPermissionGuard` for jobs/UTCP/MCP/direct invocations, validates target membership, no escalation, owner quorum/owner recovery, and audits changes.

**Exceptions:** First organisation creation and invited-user acceptance happen before the target organisation Kinship role exists. They use explicit bootstrap/invitation-domain validation; do not incorrectly wrap them in an organisation-scoped Kinship permission middleware.

## Permission contract examples

| HTTP action | Native Kinship route gate |
|---|---|
| List brands | `kinship.permission:brand.index` |
| Create brand | `kinship.role:owner,manager`, `kinship.permission:brand.store` |
| List brand members | `kinship.role:owner,manager`, `kinship.permission:brand_membership.index` |
| Add brand member | `kinship.role:owner,manager`, `kinship.permission:brand_membership.store` |
| Change brand member role | `kinship.role:owner,manager`, `kinship.permission:brand_membership.update` |
| Revoke brand member | `kinship.role:owner,manager`, `kinship.permission:brand_membership.delete` |
| Invite organisation member | `kinship.role:owner`, `kinship.permission:organisation_invitation.store` |
| Revoke organisation member | `kinship.role:owner`, `kinship.permission:organisation_membership.delete` |

The reference project's legacy `permission.authorize` alias is NOT registered here. This application's package alias is `kinship.permission`. Both role **and** permission checks are applied before the controller.

## Seeding and reconciliation

Kinship never automatically seeds itself. During new organisation onboarding, `CreateOrganisationService` runs Kinship's `PermissionCatalogSeeder` for the newly created workspace, transactionally with owner provisioning. For **existing** organisations (replace `123` with the real organisation primary key):

```bash
php artisan kinship:seed --workspace=123 --dry-run
php artisan kinship:seed --workspace=123 --sync
# Or review/update all existing workspaces with the Planner wrapper:
php artisan planner:sync-kinship-catalog --dry-run
php artisan planner:sync-kinship-catalog --sync
```

`--sync` is important after changing role grants. It reconciles **package-managed role-permission pivots**, which otherwise retain formerly-granted permissions. It does not automatically delete obsolete permission rows or direct user overrides. Audit any legacy `_` names before removing them; never mass-delete grants without checking the live database.

## Verification plan and evidence

- Dependency-free `php scripts/check_kinship_catalog.php` ensures every configured role pattern resolves to a known resource, every route permission exists and the three non-owner roles do not receive dangerous permissions. **Executed: PASS**, 90 model files, 654 model/explicit permissions, 4 predefined roles, 2 route files.
- `tests/Unit/Authorization/KinshipCatalogContractTest.php` verifies actual package discovery, role matrices, custom permissions, and route-edge middleware. **Authored; not executed**.
- `tests/Integration/Authorization/KinshipWorkspaceCatalogTest.php` provisions a workspace via user factory + real onboarding, verifies role grants and idempotent reseeding. **Authored; not executed**.
- `tests/Feature/Membership/BrandMembershipEndpointsTest.php` covers brand membership CRUD, role escalation, brand role narrowing and owner recovery. **Authored; not executed**.
- CI `.github/workflows/test.yml` now calls the static guard and all unit/feature/integration suites with a MySQL service. **CI execution still pending**.

## Scope and risks

No database migration or dependency upgrade was needed. The dot-separated permission names are a **catalogue contract change** from the interrupted underscore implementation; old direct grants and persisted roles must be reconciled before production. Routes are validated for presence of correct middleware; the full real-request middleware sequence and Kinship cache behavior require MySQL feature testing.
