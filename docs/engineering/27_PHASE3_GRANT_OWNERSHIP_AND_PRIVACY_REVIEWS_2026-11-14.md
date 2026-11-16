# Phase 3 — Grant ownership and privacy officer review (increment 27)

## Implemented

- Normalized `provider_grant_groups` and tenant-scoped `provider_grant_links`. A Meta grant is grouped by `(META_APP_ID, app-scoped user digest)` rather than Page ID; a grant may cover Facebook and Instagram connections across different organisations. Raw actor IDs are **not** stored in these group rows.
- OAuth connections record/reattach the Meta grant relationship within the same persistence transaction; local disconnect marks the link detached only after the final account for that connection is no longer active. Historical links remain for data deletion lookup.
- Meta signed deletion callback now matches both legacy `external_actor_id` connections and indexed grant groups.
- Owner-only privacy review at organisation scope. Review decisions distinguish confirmed erasure (`CLEARED`) from narrowly justified retention (`RETAINED_LEGAL`), require an evidence reference and explanation, and are recorded in an append-only review table. No controller has arbitrary non-CRUD methods; decisions use invokables.
- Requests with all targets finally resolved are `COMPLETED` or `COMPLETED_WITH_RETENTION`. Public status explains retention without disclosing identities.
- Repository contracts, model factories, opt-in local seeder, Inertia review page, web/API routes, unit/integration/feature/architecture tests.

## Non-negotiable boundaries

- Access review only as an active, authorised organisation owner with `privacy-deletion.review`; enforce Kinship at HTTP edge and re-authorize inside service (UTCP/MCP may bypass HTTP routes).
- No provider network request inside a database transaction. No remote provider revocation is performed yet.
- A review action does **not** remove records by itself. The `CLEARED` disposition is a human attestation referencing separately verified erasure evidence; no generic delete-all button.
- Never erase unrelated brands' authored campaigns solely because a Page shares an OAuth grant. Manual reviewers must identify person-level information and retention requirements.
- Prevent repeated review decisions through target-level uniqueness and optimistic state precondition; lock request before target to match erasure worker order.
- Meta HMAC key and app ID must remain stable; loss or rotation requires a controlled re-key process. Existing unlinked Meta grants can be handled by the callback's legacy identifier lookup; run the backfill command once configured (future task).

## Deployment order

1. Set `META_APP_ID`, `META_APP_SECRET`, `META_ACTOR_HMAC_KEY` (stable), HTTPS `APP_URL` and mail/privacy contact. Never place secrets in source or AI chats.
2. Install dependencies; run `php artisan migrate --pretend` and inspect the four ordered additive migrations (`180000`–`180003`).
3. Run `php artisan migrate` with a backup and maintenance window where appropriate.
4. Run `php artisan kinship:seed` / your reviewed Planner catalog sync for existing tenants. Verify owner-only special permission grant.
5. Run `php artisan planner:backfill-meta-grants` and review counts, then `php artisan planner:backfill-meta-grants --apply` only after the identity mapping is verified.
6. Run `php artisan test --filter=MetaReviewDispositionTest`, `MetaGrantOwnershipTest`, `MetaDeletionManualReviewTest`, `MetaDeletionOfficerReviewTest` and existing `MetaDataDeletionCallbackTest`, with MySQL and Kinship bootstrapped.
7. Configure the Meta callback URL, send a signed callback from Meta's test flow, confirm publicly accessible noindex/status behavior, verify no actor identifiers leak.
8. Verify grant grouping for multiple Facebook Pages and linked Instagram accounts, including cross-organisation isolated deletion and local disconnection.
9. Verify review evidence linkage, legal approval policy and source erasure in object stores, backups, exports and logs before production.

## Limitations and remaining work

- **Full Laravel/MySQL tests have not run in this container.** The author must run the listed tests on the actual development stack.
- The provider-grant link table is populated for future connections; legacy connection backfill is available via `php artisan planner:backfill-meta-grants` (dry-run) and `--apply`; anonymized/unknown actor identities cannot be reconstructed automatically.
- Scheduled legal-retention re-reviews, provider-side remote grant revocation, Meta `/debug_token` introspection, provider capability reconciliation, and export/object-store erasure are **not yet implemented**.
- Meta privacy callback is provider-specific; do not reuse its HMAC protocol for X, TikTok, YouTube or LinkedIn. Their legal and API workflows must be reviewed independently.
- A security/privacy reviewer must validate retention bases, support process, policy notice, backups and regulatory requirements before live activation.

## Separate marketing website workstream

Pensuh.com **must** be updated across Privacy Policy, Cookie Policy, Terms, Data Deletion Instructions, Demo/Contact consent, Refund and Security descriptions, plus navigation and sitemap. See `docs/compliance/PENSUH_COM_PRIVACY_AND_LEGAL_PAGES_WORKSTREAM.md`. This source change **does not edit pensuh.com**.
