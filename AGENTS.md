## Increment 66 — autonomous AI marketing orchestration (2026-11-15)

Read `docs/engineering/66_DURABLE_AUTONOMOUS_RESEARCH_AND_EDITORIAL_RESOURCES_2026-11-15.md`. A new optional web-search research gateway, durable research/resource stage runner, reviewable editorial-resource-to-draft conversion and MCP/React controls exist. Keep `PLANNER_AGENT_AUTONOMY_ENABLED=false` until local Composer/MySQL/OpenAI/MCP tests. Do not replay UNCERTAIN remote research and do not allow any AI tool to approve its own content. Agent read APIs are grant-scoped. All Passport OAuth routes remain app-owned. No live publishing enabled. Lockfiles preserved.

## Increment 62 — Storage v3/release safety (2026-11-15)

Read `docs/engineering/62_STORAGE_V3_COST_ALERTS_RELEASE_SAFETY_2026-11-15.md`. The published Tetranyble v3 `FileSystemContract` APIs are the only accepted streaming/signed URL integration, and the new adapters remain OFF until on-disk verification. `PLANNER_EMERGENCY_PUBLISH_STOP=true` is a default-on higher-priority worker stop, not an immediate command to cancel a request in flight. AI token costs require operator pricing evidence and are NOT provider invoices. Alert-mail uncertainty is terminal and never auto-replayed. Both lockfiles preserved. No batch production-accepted and Pensuh.com legal/privacy deployment remains pending.

## Planner increment 34 — immutable version-risk declarations (2026-11-14)

Read `docs/engineering/34_VERSION_RISK_DECLARATIONS_2026-11-14.md`. The two content risk declarations are nullable: unknown must match both boolean-valued approval-rule conditions. Never cast unknown to false in business logic. Declarations enter the immutable version hash and approval snapshot; legacy all-unknown hashes are unchanged. No provider publication or scheduling enabled. Apply the additive migration and execute Laravel/MySQL tests before deployment.

## Planner increment 33 — Content review approvals (2026-11-14)

Follow `docs/engineering/33_CONTENT_APPROVALS_AND_PROGRESS_2026-11-14.md`. Approval requests pin content version, target hash, media digests, policy and matched reviewer-role requirements. A new content version withdraws pending approvals. **An APPROVED review is NOT a publishing authorization for a changed version, account, media, or retired policy.** Never dispatch a provider action before revalidating all invariants. Keep `content.approve` behind manager/owner Kinship and service access; do not relax no-self constraints. Continue source-level QA, factories, repositories, and CRUD/invokable controllers. No live Pensuh.com privacy/legal pages were changed.

## Phase 4 Content & Media Studio — 2026-11-14

The source implementation is documented in `docs/engineering/32_CONTENT_MEDIA_STUDIO_2026-11-14.md`. Keep content revisions and version-media links append-only, target records ASSISTED-only until the publication state machine exists, and delegate media trust/storage to `tetranyble/storage`. All content repository reads/writes MUST scope organisation+brand. All routes require Kinship model.action and all non-HTTP entry points re-check `BrandPermissionGuard`. Never use raw DB facade inserts for test fixtures; factory states require explicit tenant associations. No live social posting without provider scope, media safety, version-bound approval, and durable idempotent publishing. Full Laravel/MySQL runtime tests are pending user validation.

# Planner application-specific architecture (mandatory)

- HTTP controllers expose **only** `index`, `store`, `update`, `destroy`, `show`, or `edit`. Any operation outside these resource actions must have its own **single-action** controller exposing only `__invoke`. Use purpose-specific names; never add `add`, `invite`, `revoke`, `changeRole`, `cancel`, `approve`, or `activate` as controller methods. Forms that do not fit the permitted actions use an invokable `Show...FormController`.
- Protect routes with Kinship `kinship.role` and `kinship.permission` middleware after organisation context; default permission discovery uses `model.action` (`catalog.separator = '.'`). Recheck authorisation and tenant/brand boundaries in application services for queue/UTCP/MCP calls.
- Persistence follows `App/Repository/Contracts`, `App/Repository/Eloquent`, and existing base-repository conventions. No Eloquent/DB queries in controllers. Use model factories for test fixtures, and existing seeders for controlled demonstration records; do not use the DB facade in tests when Eloquent factories cover the scenario.
- Add Unit, Feature, and real MySQL Integration tests for new business features. Run `npm run test:controllers` to enforce the controller contract. Never mark unexecuted tests as passed.
- Maintain `CONTINUE_HERE.md`, `docs/engineering/PHASE_TRACKER.csv`, and `docs/engineering/TEST_MATRIX.csv` after every increment for cross-chat continuity.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>


## Planner brand governance (2026-11-14)

- Use `GovernBrandClaims` and `BrandGovernanceRepositoryContract` for brand evidence/claim/policy lifecycle; never make direct tenant-scoped writes in controllers, AI/UTCP/MCP adapters or tests.
- Require approved claims supported by independently verified evidence and a trusted source. Treat URL references as *unverified metadata*, not fetched documents.
- For non-CRUD operations create `final` invokable controllers with one `__invoke` method. Use Kinship's route middleware and *also* service-level authorization.
- Every actual policy activation must preserve prior immutable versions; defer production use until MySQL generated-marker and Kinship catalog tests are verified.
- Use factories and seeders for test data; only local/testing opt-in seeders can insert demo records; never create demo sources as trusted or claims as approved.


## Phase 3 social integration boundary
Follow `docs/engineering/23_PHASE3_SOCIAL_CONNECTIONS_OAUTH_2026-11-14.md`. OAuth state is one-use, SHA-256 hashed and actor-bound. Never expose OAuth credential ciphertext or plaintext through API/Inertia, logs, jobs or tool output. Every social connection query/mutation is scoped by organisation and brand and guarded by Kinship at HTTP edge and BrandPermissionGuard in services. Use the provider adapter interfaces and the credential repository. No direct platform publishing without approved scopes and provider audits.


## Provider privacy & Pensuh.com legal workstream (2026-11-14)

- Meta's callback is app-scoped, signed and unauthenticated; never place Kinship middleware on it. Human privacy reviews require verified organisation owners, Kinship `privacy-deletion.review`, independent domain checks, evidence references and immutable decision events.
- A verified erasure is **not** the same as legal retention; `COMPLETED_WITH_RETENTION` must be represented honestly in public status. Do not add a blanket data deletion call that erases content belonging to other brands.
- Provider grant groups represent a shared authorization, not a Page or channel. Local account disconnect must not remotely revoke a shared grant until grant ownership and dependent accounts are reconciled.
- Separate Pensuh Next.js website compliance work is tracked in `docs/compliance/PENSUH_COM_PRIVACY_AND_LEGAL_PAGES_WORKSTREAM.md`; do not claim it has been published until live validation.


## Phase 29 continuity (2026-11-14)

Meta `/debug_token` inspection and privacy-retention follow-ups have been added. Keep publishing UNAVAILABLE after introspection; data-deletion acknowledgements are NOT proof of erasure. Review `docs/engineering/29_META_INSPECTION_RETENTION_FOLLOWUPS_2026-11-14.md` and `CONTINUE_HERE.md` before changing OAuth, privacy, or entitlement code. Follow the existing repository/Kinship/CRUD-invokable/factory standards. Do not automatically revoke externally shared grants. Runtime tests are not yet executed.

### Phase 3 grant revocation guidance (2026-11-14)
Do not equate an account's local disconnect with grant-wide OAuth revocation or privacy deletion. Only organisation owners can confirm a grant-wide revocation after previewing all linked accounts. Use `ProviderGrantRevocationRepositoryContract`, not raw DB queries from controllers or tests. The TikTok remote adapter remains disabled by default pending MySQL/provider verification. Never auto-retry `UNCERTAIN` provider results; never revoke a shared grant with cross-organisation links. See `docs/engineering/30_DURABLE_GRANT_REVOCATION_2026-11-14.md`.


## Increment 67 — private AI images and accountable human editorial review (2026-11-15)

Read `docs/engineering/67_PRIVATE_CREATIVE_IMAGE_AND_HUMAN_RESOURCE_REVIEWS_2026-11-15.md`. Generated images are private operator-only downloads, not canonical Storage records; **never bypass the package's upload, scanning and media ownership APIs**. `PLANNER_CREATIVE_IMAGE_ENABLED=false` is independent of research/autonomy switches. A human can revise a generated resource with optimistic resource-hash concurrency, bounded revision count and append-only events, but an MCP agent must not be given this review action. Provider attempts with ambiguous outcomes retain their `UNCERTAIN` daily budget allowance. No image ingestion or posting is considered complete until real provider and Tetranyble Storage tests. No full batch accepted, legal deployment still pending.
