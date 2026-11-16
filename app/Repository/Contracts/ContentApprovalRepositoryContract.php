<?php

namespace App\Repository\Contracts;

use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\BrandApprovalRule;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Collection;

interface ContentApprovalRepositoryContract
{
    public function target(Organisation $org, Brand $brand, ContentItem $item, string $uuid): ContentTarget;

    public function targetForRequest(Organisation $org, Brand $brand, ContentItem $item, ApprovalRequest $request): ContentTarget;

    public function activePolicy(Organisation $org, Brand $brand): ?BrandApprovalPolicy;

    public function publishingRules(Organisation $org, Brand $brand, BrandApprovalPolicy $policy): Collection;

    /** @return list<int> */
    public function reviewerRoleIds(Organisation $org, Brand $brand, BrandApprovalRule $rule): array;

    public function currentBrandRoleId(Organisation $org, Brand $brand, User $user): ?int;

    public function pending(Organisation $org, Brand $brand, ContentTarget $target): ?ApprovalRequest;

    public function create(Organisation $org, Brand $brand, ContentTarget $target, BrandApprovalPolicy $policy, User $actor, string $payloadHash, array $snapshot, int $required, bool $noSelf): ApprovalRequest;

    public function find(Organisation $org, Brand $brand, ContentItem $item, int $id, bool $lock = false): ApprovalRequest;

    public function forItem(Organisation $org, Brand $brand, ContentItem $item): Collection;

    public function hasDecision(ApprovalRequest $request, User $actor): bool;

    public function countApprovals(ApprovalRequest $request): int;

    public function decide(Organisation $org, Brand $brand, ApprovalRequest $request, User $actor, string $decision, string $comment): void;

    public function resolve(ApprovalRequest $request, string $state): void;

    public function expireDue(int $limit): int;

    public function withdrawPendingForItem(Organisation $org, Brand $brand, ContentItem $item): int;
}
