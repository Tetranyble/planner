<?php

namespace App\Repository\Eloquent;

use App\Models\ApprovalDecision;
use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\BrandApprovalRule;
use App\Models\BrandApprovalRuleRole;
use App\Models\BrandMembership;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\ContentVersion;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\ContentApprovalRepositoryContract;
use Illuminate\Support\Collection;

final class ContentApprovalRepository implements ContentApprovalRepositoryContract
{
    public function target(Organisation $org, Brand $brand, ContentItem $item, string $uuid): ContentTarget
    {
        $versionIds = ContentVersion::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('content_item_id', $item->id)->select('id');

        return ContentTarget::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereIn('content_version_id', $versionIds)->where('uuid', $uuid)
            ->with(['version.mediaLinks.asset', 'account'])->firstOrFail();
    }

    public function targetForRequest(Organisation $org, Brand $brand, ContentItem $item, ApprovalRequest $request): ContentTarget
    {
        $target = ContentTarget::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($request->content_target_id)->firstOrFail();

        return $this->target($org, $brand, $item, $target->uuid);
    }

    public function activePolicy(Organisation $org, Brand $brand): ?BrandApprovalPolicy
    {
        return BrandApprovalPolicy::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('state', 'ACTIVE')->first();
    }

    public function publishingRules(Organisation $org, Brand $brand, BrandApprovalPolicy $policy): Collection
    {
        return BrandApprovalRule::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('policy_id', $policy->id)->where('action_kind', 'PUBLISH')
            ->where('is_enabled', true)->orderBy('priority')->get();
    }

    public function reviewerRoleIds(Organisation $org, Brand $brand, BrandApprovalRule $rule): array
    {
        return BrandApprovalRuleRole::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('approval_rule_id', $rule->id)
            ->orderBy('reviewer_role_id')->pluck('reviewer_role_id')->map(fn ($id) => (int) $id)->all();
    }

    public function currentBrandRoleId(Organisation $org, Brand $brand, User $user): ?int
    {
        $roleId = BrandMembership::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('user_id', $user->id)
            ->where('state', 'ACTIVE')->value('role_id');

        return $roleId === null ? null : (int) $roleId;
    }

    public function pending(Organisation $org, Brand $brand, ContentTarget $target): ?ApprovalRequest
    {
        return ApprovalRequest::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('content_target_id', $target->id)->where('status', 'PENDING')->lockForUpdate()->first();
    }

    public function create(Organisation $org, Brand $brand, ContentTarget $target, BrandApprovalPolicy $policy, User $actor, string $payloadHash, array $snapshot, int $required, bool $noSelf): ApprovalRequest
    {
        $request = new ApprovalRequest;
        $request->forceFill([
            'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'content_target_id' => $target->id, 'payload_hash' => $payloadHash,
            'request_kind' => 'HUMAN', 'requested_by_user_id' => $actor->id,
            'required_approvals' => $required, 'status' => 'PENDING',
            'expires_at' => now()->addDays(7), 'approval_policy_id' => $policy->id,
            'matched_rule_id' => null,
            'policy_snapshot' => ['version' => 1, 'payload' => $snapshot, 'forbid_self_approval' => $noSelf,
                'required_approvals' => $required],
        ])->save();

        return $request;
    }

    public function find(Organisation $org, Brand $brand, ContentItem $item, int $id, bool $lock = false): ApprovalRequest
    {
        $versions = ContentVersion::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('content_item_id', $item->id)->select('id');
        $targets = ContentTarget::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereIn('content_version_id', $versions)->select('id');

        return ApprovalRequest::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereIn('content_target_id', $targets)->whereKey($id)
            ->when($lock, fn ($q) => $q->lockForUpdate())->firstOrFail();
    }

    public function forItem(Organisation $org, Brand $brand, ContentItem $item): Collection
    {
        $versions = ContentVersion::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('content_item_id', $item->id)->select('id');
        $targets = ContentTarget::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereIn('content_version_id', $versions)->select('id');

        return ApprovalRequest::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereIn('content_target_id', $targets)->with('decisions')->orderByDesc('id')->get();
    }

    public function hasDecision(ApprovalRequest $request, User $actor): bool
    {
        return ApprovalDecision::query()->where('organisation_id', $request->organisation_id)
            ->where('brand_id', $request->brand_id)->where('approval_request_id', $request->id)
            ->where('approver_user_id', $actor->id)->exists();
    }

    public function countApprovals(ApprovalRequest $request): int
    {
        return (int) ApprovalDecision::query()->where('organisation_id', $request->organisation_id)
            ->where('brand_id', $request->brand_id)->where('approval_request_id', $request->id)
            ->where('decision', 'APPROVE')->count();
    }

    public function decide(Organisation $org, Brand $brand, ApprovalRequest $request, User $actor, string $decision, string $comment): void
    {
        $row = new ApprovalDecision;
        $row->forceFill([
            'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'approval_request_id' => $request->id, 'approver_user_id' => $actor->id,
            'decision' => $decision, 'decided_at' => now(), 'comment' => $comment,
        ])->save();
    }

    public function resolve(ApprovalRequest $request, string $state): void
    {
        $request->forceFill(['status' => $state, 'resolved_at' => now()])->save();
    }

    public function expireDue(int $limit): int
    {
        $rows = ApprovalRequest::query()->where('status', 'PENDING')
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->orderBy('expires_at')->limit($limit)->lockForUpdate()->get();
        foreach ($rows as $request) {
            $this->resolve($request, 'EXPIRED');
        }

        return $rows->count();
    }

    public function withdrawPendingForItem(Organisation $org, Brand $brand, ContentItem $item): int
    {
        $versions = ContentVersion::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('content_item_id', $item->id)->select('id');
        $targets = ContentTarget::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereIn('content_version_id', $versions)->select('id');

        return ApprovalRequest::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereIn('content_target_id', $targets)->where('status', 'PENDING')
            ->update(['status' => 'WITHDRAWN', 'resolved_at' => now(), 'updated_at' => now()]);
    }
}
