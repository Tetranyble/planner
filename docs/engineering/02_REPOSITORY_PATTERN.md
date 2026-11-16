## Phase 0 implementation update (2026-11-14)

The original baseline section below describes **before** changes. The current code now includes `Contracts/UserRepositoryContract.php`, `Contracts/BrandScopedPersistence.php`, `Support/BrandScope.php`, `BrandScopedRepository.php`, and `Eloquent/CampaignRepository.php`. `UserRepository::save` was removed (it passed attributes as Eloquent save options). Both contracts are bound in `AppServiceProvider`. The scoped brand base is NOT a subclass of `BaseRepository`, so naked `all/find/query/count` are not available. Services must enforce Kinship/brand authorization before repositories. See `13_PHASE0_REPOSITORY_IMPLEMENTATION_2026-11-14.md`. **Full PHPUnit and MySQL tests are pending.**

---

# Mandatory repository pattern and service standards

## Observed source structure

- `app/Repository/Contracts/Persistence.php`: common persistence interface, integer primary keys, Eloquent Model return types.
- `app/Repository/BaseRepository.php`: generic Eloquent implementation including `find`, `findOrFail`, `findBy`, `create`, `update`, `delete`, `paginate`, `exists`, `chunk`, `query`.
- `app/Repository/Eloquent/UserRepository.php`: one concrete repository.
- `app/Providers/AppServiceProvider.php`: currently binds only a request-scoped `CurrentOrganisation`; domain-specific repository bindings do **not** yet exist.

**Detected defect / next-phase test:** `UserRepository::save(array $attributes)` calls `$this->model->save($attributes)`; Eloquent's `save` accepts **options**, not a mass-assignment attribute map. Replace with a semantically correct repository operation, test it, or remove the method if unused. Do not copy this pattern.

## Proposed contract for future entities (illustrative; not yet implemented)

```php
namespace App\Repository\Contracts;

use App\Models\Campaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CampaignRepository
{
    public function findForBrand(int $organisationId, int $brandId, int $campaignId): ?Campaign;
    public function paginateForBrand(int $organisationId, int $brandId, int $perPage = 20): LengthAwarePaginator;
    public function createForBrand(int $organisationId, int $brandId, array $attributes): Campaign;
    public function saveForBrand(int $organisationId, int $brandId, Campaign $campaign, array $attributes): Campaign;
}
```

```php
namespace App\Repository\Eloquent;

use App\Models\Campaign;
use App\Repository\BaseRepository;
use App\Repository\Contracts\CampaignRepository as CampaignRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CampaignRepository extends BaseRepository implements CampaignRepositoryContract
{
    public function __construct(Campaign $model) { parent::__construct($model); }

    public function findForBrand(int $organisationId, int $brandId, int $campaignId): ?Campaign
    {
        return $this->model->newQuery()
            ->where('organisation_id', $organisationId)
            ->where('brand_id', $brandId)
            ->whereKey($campaignId)->first();
    }

    // Implement other methods strictly with both tenant and brand predicates.
}
```

This is an **architecture sketch**; use `php artisan make:class --no-interaction` and actual installed Laravel APIs and write complete tested code in the implementation phase. Bind `CampaignRepositoryContract::class` to `App\Repository\Eloquent\CampaignRepository::class` through `AppServiceProvider::register()`.

## Repository rulebook

1. **Every persisted domain has an explicit contract and Eloquent implementation** when it needs domain queries or updates. Use the existing `BaseRepository` only where safe. Keep controllers unaware of `DB::table`, model query chains and transactions.
2. Do not expose a naked, mutable Builder to services/controllers. Existing `BaseRepository::query()` is an escape hatch to be limited to private repository methods; prohibit new calling sites outside the repository layer.
3. **Brand-scoped records never call inherited unscoped `all`, `paginate`, `find`, `findBy`, `count`, `exists`, `updateOrCreate`** from application services. Either override/remove those methods for brand repositories or enforce scope through a dedicated context-scoped repository base whose correctness is tested. A wrong unscoped query is a security flaw.
4. Use typed domain-specific query methods (`findDueJobsForUpdate`, `getPendingApprovalsForBrand`, `findLeadByNormalisedEmail`) rather than leaking arbitrary query predicates.
5. Only repositories encapsulate Eloquent/database access; application services own transactions and invariants; domain policy classes own authorization; controllers own HTTP I/O; adapters own provider HTTP mapping.
6. Scope every `update`, `delete`, `upsert`, `findOrCreate` and uniqueness check to the organisation and brand as appropriate. Avoid mass assignment of tenant IDs from user request payloads; use authorised context.
7. Repository contracts should expose interface types and typed data/DTOs; record methods with null/exception behavior clearly; don't hide errors behind `false`.
8. Repositories must not send emails, publish posts, call AI, dispatch provider HTTP, or decide whether a person is authorized. Those are service/adapter concerns.
9. DB writes requiring multiple records must be wrapped in a service-level transaction; write outbox records in the same transaction; dispatch asynchronously after commit.
10. External-provider adapters must be mockable behind interfaces and covered by contract tests; never call real networks in ordinary unit tests.
11. Use Eloquent relationship models and factories where appropriate, and do not introduce repositories for simple read-only DTO transformations without a persistence need.
12. Preserve model scopes for defense in depth, but never mistake a global scope alone for complete access control. Negative cross-tenant tests are required.

## Contract + transaction example (service outline)

```php
final class CreateCampaignService
{
    public function __construct(
        private \App\Repository\Contracts\CampaignRepository $campaigns,
        private \App\Authorization\BrandAuthorizer $authorizer,
    ) {}

    public function execute(\App\Models\User $actor, \App\Models\Brand $brand, array $attributes): \App\Models\Campaign
    {
        $this->authorizer->authorize($actor, $brand, 'campaign.manage');
        return \Illuminate\Support\Facades\DB::transaction(fn () =>
            $this->campaigns->createForBrand((int) $brand->organisation_id, (int) $brand->id, $attributes)
        );
    }
}
```

The example intentionally omits full validation, a request DTO, event/outbox and tests to stay focused on responsibilities; these are required in production.

## Mandatory tests for each repository

- Unit: exercise service logic with mocked **contract**; verify successful path, authorization denial, invalid transition, dependency failure.
- MySQL integration: repository create/read/update/delete and tenancy boundaries; unique constraints; composite FKs; transaction rollback; ordering/pagination; concurrent claims where applicable.
- Feature: endpoint or job reaches the service through correct contract binding; unauthorized and cross-brand requests fail even when an ID exists.
- Static rule: CI can scan for `Model::query()` or `DB::table()` in `app/Http/Controllers` and new service classes, allowing documented exceptions only.
- Test actual Eloquent behavior: don't stub the repository when verifying the repository's SQL or transaction semantics.

## Naming and file layout per feature

```text
app/Repository/Contracts/{Domain}Repository.php
app/Repository/Eloquent/{Domain}Repository.php
app/Services/{Domain}/{Verb}{Domain}Service.php
app/Http/Controllers/Api/V1/{Domain}Controller.php
app/Http/Requests/Api/V1/{Domain}/{Action}Request.php
app/Http/Resources/Api/V1/{Domain}Resource.php
app/Policies/{Domain}Policy.php (when needed)
tests/Unit/Services/{Domain}/{Service}Test.php
tests/Feature/Api/V1/{Domain}Test.php
tests/Integration/Repository/{Domain}RepositoryTest.php
```

Follow project conventions and use the installed Laravel generator when creating code. `tests/Integration` requires PHPUnit suite registration before use.
