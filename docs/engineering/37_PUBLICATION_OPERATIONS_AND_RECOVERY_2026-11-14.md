# Increment 37 — Publication operations and conservative lease recovery

Baseline: `Planner_Batch1_Publishing_Foundation_2026-11-14.zip` (increment 36).

## Implemented

- Read-only tenant- and brand-scoped publication operations dashboard and job history via existing approval read permission and secondary `BrandPermissionGuard` checks.
- `PublicationOperationsRepositoryContract` and scoped Eloquent implementation. Cross-brand requests are 404 and queries never return provider response bodies, tokens, or secret request identifiers.
- Command `planner:recover-publication-leases` (scheduled every five minutes, one server, no overlap) and `--dry-run`. Only expired `LEASED` and `PUBLISHING` rows are candidates, rechecked under row lock.
- Expired `PUBLISHING` becomes `UNCERTAIN` with an append-only `publication_events` entry and a sanitized attempt outcome. This is NOT provider-confirmed failure and must never be automatically replayed.
- Expired `LEASED` returns to `AWAITING_USER`, not to `SCHEDULED`, also with append-only event evidence. Both require operator review.
- Existing React publication desk now has monitoring counts, states and event/attempt history. All functionality remains read-only except the previously implemented held-plan creation/cancel.
- New pure domain recovery policy, PHPUnit unit source and Node architectural tests.

## Routes

GET `/api/v1/organisations/{organisation:uuid}/brands/{brand:uuid}/content/publication-operations`
GET `/api/v1/organisations/{organisation:uuid}/brands/{brand:uuid}/content/publication-operations/{jobId}`

Both use `kinship.permission:approval_request.index` at the route edge and `BrandPermissionGuard` in service. No user-facing state-change endpoint was introduced.

## Safety and incompleteness

The new recovery command can update ONLY expired legacy/in-flight records. It does not acquire leases, activate held intentions, publish content or call providers. Publishing remains disabled: no approved provider dispatch adapter, no safe remote retry policy, and no verified provider app permission/quota testing. `can_dispatch=false` remains a hardcoded UI/API invariant. An `UNCERTAIN` state needs external evidence and independently authorized reconciliation before any further action.

Run Laravel/MySQL integration, Kinship auth, Storage integrity, browser E2E and provider live capability tests in a correctly provisioned environment. Do not infer those passed from lint/static checks. On existing MySQL only run additive migrations; never `migrate:fresh` on non-disposable data.

## Next substantial batch

Evidence-driven reconciliation desk (independent reviewers, typed immutable proof, no blind resends), provider-specific delivery adapter contracts and dry-run simulations, then a staged scheduling dispatcher under explicit remote-kill switch with complete end-to-end and provider sandbox verification.
