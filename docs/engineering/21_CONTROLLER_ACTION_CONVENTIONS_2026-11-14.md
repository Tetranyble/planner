# Architecture decision — Laravel Controller Conventions (2026-11-14)

## Mandatory across API, Inertia and authentication controllers

**Only permitted public controller methods:** `index`, `store`, `update`, `destroy`, `show`, `edit`. No `create`, `add`, `revoke`, `invite`, `cancel`, `accept`, `changeRole`, `profile`, `voice`, `claim` or other custom methods.

**Non-resource actions** have one dedicated controller class with exactly one public `__invoke` method. Examples: `AcceptOrganisationInvitationController`, `ShowBrandCreationFormController`, future `ApproveContentController`, `RetryPublicationController`, `ActivateApprovalPolicyController`.

For ordinary resource operations, use the standard methods rather than single-action controller proliferation. Example: `BrandMembershipController@index/store/update/destroy`. Organisation invitation issuance/cancellation use `OrganisationInvitationController@store/destroy`; acceptance is invokable because it is a domain command, not a resource update. Client-facing route *names* and URIs currently remain backward compatible while controllers have been normalized.

## Enforcement

1. `tests/Frontend/controller-conventions.test.mjs` statically verifies all controller public methods, one-method invokables, route targets and Kinship permissions. Run `npm run test:controllers`.
2. `tests/Unit/Architecture/ControllerConventionTest.php` verifies naming across the whole controller directory.
3. `tests/Feature/Architecture/ControllerActionRoutingTest.php` checks Laravel route bindings and authorization middleware. Requires successful Laravel boot but does not require seed data.
4. CI runs the Node check before frontend build and includes the PHPUnit Unit/Feature suites.

## Layering and authorization

```
Route: auth + organisation.context + kinship.role + kinship.permission
    -> FormRequest: validated input
    -> Controller: resource action or __invoke, HTTP translation only
    -> Application service: tenant/member/Kinship checks and business policies
    -> Repository contract / Eloquent implementation
    -> MySQL: composite tenancy constraints and durable audit
```

Controllers must not issue model queries, `DB::table()`, or authorisation workarounds. State transitions, transactions, outbox and audit writes belong in application services and repositories, not controllers. Models, factories and seeders own test-data structure; tests should use factories for fixtures rather than direct DB facade inserts when model factories exist.

The adapter layer (UTCP, MCP, queue/worker) must call the same application services; request middleware does not replace service-level authorization.

## Required tests for a newly added controller

- Unit: service/domain behavior and invalid transition handling.
- Feature: guest, unverified, suspended membership, Kinship wrong role/permission, cross-organisation and cross-brand denial; successful API/Inertia responses, bad payloads.
- Integration: actual MySQL constraints, repository predicates, transaction rollback, concurrency where relevant.
- Contract: routes resolve to permitted methods and bind exact Kinship abilities.
- Frontend: required routes and UI form states.

**Never classify a slice as verified solely because lint or static contract checks pass.**
