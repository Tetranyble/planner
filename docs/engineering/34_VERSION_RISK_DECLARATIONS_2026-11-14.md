# Increment 34 — Version-bound risk declarations and precise approval matching

**Baseline:** Increment 33 approval workflows; source remains a complete Planner project.

## Implemented

- Additive migration `2026_11_14_231000_add_content_version_risk_declarations.php`: two nullable, immutable-version boolean declarations. `NULL` is an explicit unknown, NOT a false value. Existing rows remain unknown. The migration is additive and must be applied via `php artisan migrate`, not `migrate:fresh`, to an existing tenant DB.
- Two FormRequests accept nullable booleans `ai_generated` and `includes_sensitive_claim` on create and revision. Both Inertia forms have explicit Yes/No/Not assessed selectors. API clients may omit both values for backward compatibility; omission remains conservatively unclassified.
- Repository contract and implementation persist declarations in `content_versions` and include non-null declarations in content SHA-256 hashing. Unknown/unknown retains the legacy content hash unchanged.
- Approval rule matching checks declared version risks against conditional brand publishing rules. A known opposite boolean excludes that rule. Unknown matches BOTH true and false rules, requiring the strictest applicable review. Existing policy-wide maximum quorum, role intersections, and minimum one human review remain unchanged.
- A version's risk declarations become part of the exact approval snapshot only when at least one is known. This preserves historical approval payload hashes when both declarations are unknown. Changing metadata requires a new version; pending reviews are withdrawn by the existing service.
- Unit tests for unknown matching and content hashing; MySQL integration test for tri-state persistence/immutable history; frontend architecture tests for requests/UI/hash/migration.

## Verified in this environment

- All PHP source files under app/database/migrations/tests/routes linted without syntax errors.
- Focused frontend suites: **13/13 PASS**.
- Dependency-free PHP risk/hash smoke PASS; repository boundary guard PASS; BrandScope smoke 10 assertions PASS.
- Entire frontend tests: **66 passed / 3 test files failed**, because missing local `@babel/parser` node dependency; rerun after `npm ci`.

## Not verified / follow-up

- Laravel PHPUnit Unit/Feature/Integration, actual MySQL migration and rollback, Eloquent scoped persistence, concurrent reviewer decision races, full Vite and browser UI remain unexecuted. Package-specific Kinship/Storage runtime and provider OAuth remain unverified.
- Typed booleans are **editor declarations**, not trustworthy detection or verification. Policy rule action_kind/priority semantics are not expanded in this increment; current conservative aggregation is retained. Publication and scheduling remain disabled.
- Complete the release gate, then build a publish-readiness **read-only** check with full capability/credential and approved snapshot validations before any scheduling or provider side effect.
- Pensuh.com legal/privacy page updates remain tracked, **not deployed**.

## Local commands

```bash
composer install
npm ci
php artisan migrate --env=testing # only on a disposable test DB first
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
node --test tests/Frontend/content-risk-declarations.test.mjs
npm run build
```
