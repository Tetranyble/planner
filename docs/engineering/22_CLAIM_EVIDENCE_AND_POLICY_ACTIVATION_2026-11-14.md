# Phase 2 — Evidence-backed claim governance and policy activation (14 Nov 2026)

**Status: IMPLEMENTED / RUNTIME UNVERIFIED.** Baseline: `Planner_Phase2_Brand_Strategy_Controller_Conventions_2026-11-14.zip`. No package version changes; original Laravel migrations left intact. One additive migration: `2026_11_14_160000_govern_brand_evidence_and_policy_activation.php`.

## Domain decisions

- Knowledge sources are **manual references** or **URL metadata only**. There is no automatic remote URL fetching, web scraping or media upload in this increment. Tetranyble Storage remains the future owner of uploaded media. Unreviewed sources start `PENDING`; independent users mark them `TRUSTED` or `REJECTED`.
- Evidence belongs to one claim and one source, with composite tenant/brand foreign keys. Evidence begins `PENDING`; a separate authorized reviewer can mark it `VERIFIED` only when its source is already `TRUSTED`.
- Draft claims enter `IN_REVIEW` via an explicit non-CRUD action. Approval requires a trusted source with independently verified evidence for **all** claim categories, as well as the existing category-specific claim-policy checks and date window. Claim authors cannot review their own claims. Decisions append to `brand_claim_review_events` and audit logs. An APPROVE decision also freezes the exact evidence/source content hashes in normalized `brand_claim_review_evidence` records, enabling later provenance audits even if the source is eventually withdrawn.
- New publishing approval policies remain `DRAFT`. An owner can activate a policy only when it is human-review-only, prevents self-approval, requires >=1 approver, and contains at least one enabled publish rule. Activation retires any previous active policy within a brand-serialized transaction. A generated nullable column plus unique index ensures at most one ACTIVE policy per brand in MySQL.
- Policy versions are edited only as drafts through the application. Eloquent guards prevent changing active/retired policies and their rules; raw direct SQL outside application services is **not** yet protected with DB triggers. Approved claims and trusted source records receive Eloquent change guards, but raw SQL can still bypass them. Treat direct database write access as privileged.
- Historical sources and claims created before this migration have nullable `submitted_by_user_id`/`created_by_user_id`. They remain pending by default; old author identity cannot be inferred and should be reconciled separately. Do not claim historic four-eyes proof where attribution is absent.
- All route mutations use `kinship.role` and `kinship.permission` after the authenticated organisation context. Mutating services re-check Kinship plus brand role in `BrandPermissionGuard` for non-HTTP UTCP/MCP/queue callers. Organisation and brand locks serialize cross-module membership and governance writes.
- Claims and approval policies do not become publishable through draft creation alone. The publishing engine (future phase) must re-evaluate claim validity/evidence trust and exact activated policy version at execution time.

## API contract

All endpoints below are relative to `/api/v1/organisations/{organisation:uuid}/brands/{brand:uuid}/strategy` and mirrored in the existing Inertia workspace route group:

| Method | Endpoint | Controller | Ability |
|---|---|---|---|
| POST | `/knowledge-sources` | `BrandKnowledgeSourceController@store` | `brand_knowledge_source.store` |
| POST | `/knowledge-sources/{sourceUuid}/review` | `ReviewBrandKnowledgeSourceController@__invoke` | `knowledge-source.verify` |
| POST | `/claims/{claimUuid}/evidence` | `BrandClaimEvidenceController@store` | `brand_claim_evidence.store` |
| POST | `/evidence/{evidenceUuid}/review` | `ReviewBrandClaimEvidenceController@__invoke` | `claim-evidence.verify` |
| POST | `/claims/{claimUuid}/submit-review` | `SubmitBrandClaimForReviewController@__invoke` | `claim.submit-review` |
| POST | `/claims/{claimUuid}/review` | `ReviewBrandClaimController@__invoke` | `claim.review` |
| POST | `/approval-policies/{policyUuid}/activate` | `ActivateBrandApprovalPolicyController@__invoke` | `approval-policy.activate` |

`ReviewSourceRequest` / `ReviewEvidenceRequest`: `{ decision: VERIFY|REJECT, rationale: 10..2000 characters }`.
`ReviewClaimRequest`: `{ decision: APPROVE|REJECT, rationale: 10..2000 characters }`.
Knowledge source create accepts `title`, `source_type: MANUAL|WEB`, `source_url` (for WEB), `content_text` (for MANUAL). Input is never interpreted as a ready-to-publish marketing claim.

## Implementation inventory

- `app/Repository/Contracts/BrandGovernanceRepositoryContract.php`, `app/Repository/Eloquent/BrandGovernanceRepository.php` — tenant-bounded source, evidence, claim and versioned policy persistence.
- `app/Services/BrandStrategy/GovernBrandClaims.php` — authorization, state transition, locks, transactions, audit.
- `app/Domain/BrandStrategy/ApprovalPolicyActivationRules.php` — pure activation business rules. Existing `ClaimPublicationEligibility` is reused.
- `app/Enums/EvidenceReviewState.php` and new `BrandClaimReviewEvent` + `BrandClaimReviewEvidence` models/factories.
- Source, evidence and policy Eloquent models updated for timestamp correctness and immutability protection.
- `config/kinship.php` contains five special permissions plus model-discovered create abilities; Manager and Contributor grants are explicit, not wildcarded. Owner retains Kinship `*`.
- REST and Inertia thin controllers, DTO validation via FormRequest and controls in `resources/js/Pages/Organisations/Brands/Strategy.jsx`.
- Factories updated with state helpers; `BrandGovernanceDemoSeeder` is explicitly opt-in, idempotent and limited to local/testing. It does not create trusted sources or approved claims.

## Test inventory and evidence

- **Unit authored:** `ApprovalPolicyActivationRulesTest`; existing `ClaimPublicationEligibilityTest` reused. Pure service tests against mocked dependencies may be added once runtime DI is available.
- **Feature authored, MySQL pending:** `BrandGovernanceEndpointsTest` validates independent review, self-review rejection, unsupported approval conflict, cross-brand isolation, duplicate decision prevention, and version activation.
- **Integration authored, MySQL pending:** `BrandGovernanceIntegrityTest` validates composite foreign keys, review history scoping, active-policy generated uniqueness, factory relationships. `BrandGovernanceDemoSeederTest` asserts repeatable, safe seeded state.
- **Executed:** PHP lint on source tree; Node governance route/controller/UI contract tests; broader controller/theme/front checks; Babel JSX parsing; standalone policy rules check. See `CONTINUE_HERE.md` for exact evidence.
- **Not executed:** Laravel/PHPUnit feature and integration tests, MySQL migration/rollback, Kinship live grants, Vite production build, browser accessibility. The container lacks PHP `dom`, `mbstring`, `xmlwriter` and PDO MySQL; Node dependencies require a proper Linux `npm ci`.

## Migrations and rollout

1. On a disposable MySQL 8.0 database, execute `php artisan migrate:fresh --env=testing --force`, rollback one step, then migrate again. Verify the generated nullable `active_marker` and unique composite key.
2. On an existing development database, back up and use `php artisan migrate`; preflight for multiple existing ACTIVE policies and duplicate source/claim records. Do not run `migrate:fresh` on an existing working database.
3. Run the Kinship catalog synchronization in dry-run mode, review grants, then sync the catalogue for each organisation. These new special permissions do not appear until reconciliation runs.
4. Run `php artisan test --testsuite=Unit`, `--testsuite=Feature`, and `--testsuite=Integration`, plus `npm ci`, `npm run test:brand-strategy-ui`, `npm run test:controllers`, `npm run build`.
5. Verify two-user approval scenarios in UI; one-person organisations cannot self-verify claims they authored. Record runtime evidence in the tracker and update status.

## Open engineering questions

- Source replacement/revocation and freshness schedules (currently no automatic expiry or periodic URL validation); expose only after designing evidence invalidation rules and storage provenance.
- Database-level immutability triggers for published/approved records and workflow decision history, to supplement model guards and avoid privileged direct-write bypass.
- Concurrency tests with multiple connections/workers, including active policy version switching during running publication jobs.
- Pagination and full-text search for sources/evidence; strategy snapshot is intentionally capped (100 sources, 200 evidence records).
- Exact marketing publication claim checks; no live publishing service exists yet. The next natural slice is the social-provider connection boundary and capability catalogue.
