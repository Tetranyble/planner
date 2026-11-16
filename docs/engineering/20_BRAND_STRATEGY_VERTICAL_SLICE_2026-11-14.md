# Phase 2 — Normalized Brand Strategy (2026-11-14)

## Status: IMPLEMENTED / RUNTIME UNVERIFIED

**Baseline:** `Planner_Phase1_Brand_Membership_Kinship_Catalog_2026-11-14.zip` with the existing Laravel 13, React/Inertia, shadcn theme, Kinship, Tetranyble Storage, repository contracts, and normalized MySQL schema. This implementation does **not** change existing migrations and does not override the user's source.

## Delivered

1. `BrandStrategyRepositoryContract` and `Repository/Eloquent/BrandStrategyRepository`: organisation+brand scoped reads and writes for normalized profiles, audience segments with roles/goals/pain points, voice profiles with rules and vocabulary, brand claim policy, draft claims, draft approval policies with their rules.
2. `ManageBrandStrategy` service: checks Kinship and brand membership, verifies organisation/brand association, and serializes writes in a MySQL transaction with an audit event. All callers (REST, Inertia, future UTCP/MCP) use the service boundary.
3. REST and Inertia routes: read snapshot, update positioning and claims policy, create audience/voice, create draft claim and draft approval policy. Kinship permission middleware is retained at the route edge.
4. React/Inertia strategy workspace: sectioned forms, read-only state where not authorized, draft-only claim and policy flows, existing theme/shadcn tokens.
5. Eloquent factory coverage: 19 strategy-related factories populated or completed for scoped relational fixtures. `BrandStrategyDemoSeeder` is opt-in, idempotent and local/testing only; it never invents customers or creates approved claims.
6. Unit test `ClaimPublicationEligibilityTest`, feature test `BrandStrategyEndpointsTest`, integration test `BrandStrategyFactoriesTest`, and frontend `brand-strategy-pages.test.mjs` authored. Runtime PHP/MySQL test execution remains pending.

## API endpoint contract (preserved URLs)

| Method | Path suffix on `/api/v1/organisations/{organisation}/brands/{brand}/strategy` | Controller method | Kinship ability |
|---|---|---|---|
| GET | `/` | `BrandStrategyController@show` | `brand_profile.index` |
| PATCH | `/profile` | `BrandProfileController@update` | `brand_profile.update` |
| POST | `/audiences` | `BrandAudienceSegmentController@store` | `brand_audience_segment.store` |
| POST | `/voices` | `BrandVoiceProfileController@store` | `brand_voice_profile.store` |
| PATCH | `/claims/policy` | `BrandClaimPolicyController@update` | `brand_claim_policy.update` |
| POST | `/claims` | `BrandClaimController@store` | `brand_claim.store` |
| POST | `/approval-policies` | `BrandApprovalPolicyController@store` | `brand_approval_policy.store` |

Resource writes are thin controllers. Never add non-CRUD methods to any controller. See `21_CONTROLLER_ACTION_CONVENTIONS_2026-11-14.md`.

## Safety invariants

- Do not approve claims on creation: user-provided `status` cannot bypass DRAFT.
- Do not activate approval policies on creation: every new version remains DRAFT.
- Do not use an unauthorised brand's strategy, even when Kinship grants a workspace-wide permission.
- Do not allow a strategy record to refer to an unrelated organisation or brand. The existing composite foreign keys and repository scoping remain requirements for the disposable MySQL runtime test.
- Do not interpret the demo seeder as proof of any marketed product capability.
- Do not allow UTCP/MCP agents to bypass the service or Kinship policy boundary.

## Tests and gating

Executed here: frontend contract tests, standalone repository/tenant/permission checks, PHP lint, and static migration structure check. See current continuation file for exact numbers. **Not executed:** full PHPUnit test suites, Kinship and MySQL integration tests, Vite build, browser accessibility tests and actual provider integrations. The local container cannot bootstrap the complete application without the expected environment configuration and dependencies. These remain release gates.

## Next required implementation

After runtime verification: evidence attachments and review workflow for claims, immutable activation for publishing approval policies, reference/source provenance, and the full audience geography hierarchy. Keep role/permission grants in Kinship and provider assets in Tetranyble Storage. Use factories and existing seeders to create the test graph, not raw table inserts.
