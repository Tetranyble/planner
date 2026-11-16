# Increment 51 — Provider text publishing request contracts (offline)

**Source baseline:** Increment 50 full source archive. **Acceptance:** This is implementation groundwork for Batch 1, not completed Batch 1. All live dispatch remains disabled.

## Delivered

- `app/Domain/Publishing/LiveContracts/PreparedProviderRequest.php`: immutable credential-free request specification and safe metadata projection.
- `TextPostRequestCompiler.php`: documented text-post request shapes for Facebook Pages (`POST /v27.0/{page-id}/feed`), LinkedIn (`POST /rest/posts`), and X (`POST /2/tweets`); strict destination/account validation. These compilers do not perform HTTP and are not dispatch permissions.
- `ProviderReceiptInterpreter.php`: conservative response interpretation; 2xx without provider receipt is **UNCERTAIN**, not success. Response metadata is sanitized via existing `DispatchOutcome`.
- `ProviderTextContractHarness.php`: 18 platform/scenario fixtures, strict no-network/no-credential/no-write guarantees; verifies conservative retry decisions without proven provider idempotency.
- `tests/Standalone/provider_text_contracts.php`: 112 portable assertions run without Laravel or database.

**Documentation sources validated 2026-11-14**: [Meta Page Feed](https://developers.facebook.com/docs/graph-api/reference/page/feed/), [LinkedIn Posts API](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api?tabs=curl&view=li-lms-2026-06), [X Posts API documentation](https://docs.x.com/). Provider terms, permissions, account access, schema versions and rate quotas must be rechecked before live integration.

## Explicit limitations / safety gates

1. **No production transport** exists for these specs; they are offline request contract compilers, not real publishing adapters.
2. LinkedIn restricted API access and organisation author permissions must be verified; LinkedIn version is an explicit parameter, default `202606`, not a claim of an indefinitely supported version.
3. Facebook requires a valid Page access token and approved Page-scoped publishing permissions; X requires user-context access and API entitlements.
4. Instagram, TikTok and YouTube media upload/container/publish workflows are not implemented here; they intentionally cannot use the text compiler.
5. Actual worker dispatch, credentials, provider confirmations, duplicate-post prevention and independent runtime tests remain open.
6. A provider-issued receipt may still require read-after-write reconciliation; our fixture success is not a verified publication.
7. Operator permissions, Tetranyble Storage and Kinship contracts are not modified.

## Verification executed

- `php tests/Standalone/provider_text_contracts.php` → **112 assertions PASS**.
- `php scripts/check_repository_boundaries.php` → PASS (37 Eloquent repositories).
- `php scripts/check_kinship_catalog.php` → PASS (112 model files, 816 permissions, four roles).
- `find app database tests -name '*.php' -print0 | xargs -0 -P 8 -n 1 php -l` → **1021 PHP files PASS**.
- `node --test tests/Frontend/*.test.mjs` → **120/123 PASS**, three failures remain missing local `@babel/parser` installation.
- **Not executed:** real Laravel PHPUnit, disposable MySQL, OAuth integration, browser E2E, remote publishing.

## Engineering estimated source-development coverage (not runtime readiness)

| Batch | Estimate | Status |
|---|---:|---|
| Batch 1 — Publishing Engine | 43% | IN PROGRESS |
| Batch 2 — Content Operations | 65% | IN PROGRESS |
| Batch 3 — AI Marketing Intelligence | 50% | IN PROGRESS |
| Batch 4 — Analytics & Automation | 45% | IN PROGRESS |
| Batch 5 — Integrations & Production | 20% | IN PROGRESS |

Unweighted overall estimate: **44.6%**, rounded **45%**. Completed batches: **0/5**.

## Next substantial deliverable

Implement verified transport interfaces and six-provider media workflows behind feature flags, durable lease-fenced dispatch of actual publication attempts with enforced account/approval checks, provider receipts, and reconciliation. Keep the live kill switch OFF until proper integration and user operational authorization. Final system testing remains assigned to user.
