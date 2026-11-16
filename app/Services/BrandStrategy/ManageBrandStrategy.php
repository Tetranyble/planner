<?php

namespace App\Services\BrandStrategy;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\BrandStrategyRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/** Commands are permission-checked even when invoked outside HTTP (UTCP/MCP/queues). */
final class ManageBrandStrategy
{
    public function __construct(
        private readonly BrandPermissionGuard $guard,
        private readonly BrandStrategyRepositoryContract $repository,
        private readonly AuditEventRepositoryContract $audit,
    ) {}

    public function can(User $actor, Organisation $organisation, Brand $brand, string $permission): bool
    {
        return (int) $brand->organisation_id === (int) $organisation->id
            && $this->guard->allows($actor, $brand, $permission);
    }

    public function read(User $actor, Organisation $organisation, Brand $brand): array
    {
        $this->authorize($actor, $organisation, $brand, 'brand_profile.index');

        return $this->repository->snapshot($organisation, $brand);
    }

    public function updateProfile(User $actor, Organisation $organisation, Brand $brand, array $data): void
    {
        $this->execute($actor, $organisation, $brand, 'brand_profile.update', 'brand.profile.updated',
            fn () => $this->repository->saveProfile($organisation, $brand, $actor, $data));
    }

    public function addAudience(User $actor, Organisation $organisation, Brand $brand, array $data): string
    {
        return $this->execute($actor, $organisation, $brand, 'brand_audience_segment.store', 'brand.audience.created',
            fn () => $this->repository->createAudience($organisation, $brand, $data));
    }

    public function addVoice(User $actor, Organisation $organisation, Brand $brand, array $data): string
    {
        return $this->execute($actor, $organisation, $brand, 'brand_voice_profile.store', 'brand.voice.created',
            fn () => $this->repository->createVoice($organisation, $brand, $data));
    }

    public function updateClaimPolicy(User $actor, Organisation $organisation, Brand $brand, array $data): void
    {
        $this->execute($actor, $organisation, $brand, 'brand_claim_policy.update', 'brand.claim_policy.updated',
            fn () => $this->repository->saveClaimPolicy($organisation, $brand, $data));
    }

    public function addDraftClaim(User $actor, Organisation $organisation, Brand $brand, array $data): string
    {
        return $this->execute($actor, $organisation, $brand, 'brand_claim.store', 'brand.claim.drafted',
            fn () => $this->repository->createDraftClaim($organisation, $brand, $actor, $data));
    }

    public function addDraftApprovalPolicy(User $actor, Organisation $organisation, Brand $brand, array $data): string
    {
        return $this->execute($actor, $organisation, $brand, 'brand_approval_policy.store', 'brand.approval_policy.drafted',
            fn () => $this->repository->createDraftApprovalPolicy($organisation, $brand, $actor, $data));
    }

    private function authorize(User $actor, Organisation $organisation, Brand $brand, string $permission): void
    {
        if (! $this->can($actor, $organisation, $brand, $permission)) {
            throw new AuthorizationException('You do not have brand strategy access.');
        }
    }

    private function execute(User $actor, Organisation $organisation, Brand $brand, string $permission, string $event, callable $command): mixed
    {
        $this->authorize($actor, $organisation, $brand, $permission);

        return DB::transaction(function () use ($actor, $organisation, $brand, $permission, $event, $command) {
            // Lock parent brand so role revocations/changes and configuration writers serialize
            // where the membership layer observes the same brand locking convention.
            $locked = Brand::query()->where('organisation_id', $organisation->id)
                ->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $organisation, $locked, $permission);
            $result = $command();
            $this->audit->record($organisation,$actor,$event,'brand_strategy',(string) $brand->id,$brand);

            return $result;
        }, attempts: 3);
    }
}
