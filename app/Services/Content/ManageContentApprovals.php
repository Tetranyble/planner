<?php

namespace App\Services\Content;

use App\Authorization\BrandPermissionGuard;
use App\Domain\Content\ApprovalPayloadSnapshot;
use App\Domain\Content\RiskRuleMatcher;
use App\Enums\Connection\SocialAccountStatus;
use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\ContentApprovalRepositoryContract;
use App\Repository\Contracts\ContentStudioRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Exact immutable review of a brand-scoped target. NO publication side effect. */
final class ManageContentApprovals
{
    public function __construct(
        private readonly BrandPermissionGuard $guard,
        private readonly ContentStudioRepositoryContract $studio,
        private readonly ContentApprovalRepositoryContract $approvals,
        private readonly StorageMediaInspector $media,
        private readonly AuditEventRepositoryContract $audit,
    ) {}

    public function can(User $actor, Organisation $org, Brand $brand, string $permission): bool
    {
        return (int) $brand->organisation_id === (int) $org->id && $this->guard->allows($actor, $brand, $permission);
    }

    private function authorize(User $actor, Organisation $org, Brand $brand, string $permission): void
    {
        if (! $this->can($actor, $org, $brand, $permission)) {
            throw new AuthorizationException('Brand approval permission denied.');
        }
    }

    private function write(User $actor, Organisation $org, Brand $brand, string $permission, callable $callback): mixed
    {
        $this->authorize($actor, $org, $brand, $permission);

        return DB::transaction(function () use ($actor, $org, $brand, $permission, $callback) {
            // Use same lock ordering as Content Studio; request decisions serialize per brand.
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $locked = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)
                ->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $locked, $permission);

            return $callback();
        }, 3);
    }

    /** System-only maintenance: no OAuth, publication or external side effects. */
    public function expireDue(int $limit): int
    {
        return DB::transaction(fn () => $this->approvals->expireDue(max(1, min($limit, 500))), 3);
    }

    public function listing(User $actor, Organisation $org, Brand $brand, string $itemUuid): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.index');
        $item = $this->studio->findItem($org, $brand, $itemUuid);

        return $this->approvals->forItem($org, $brand, $item)->map(fn (ApprovalRequest $request) => $this->present($request))->values()->all();
    }

    private function present(ApprovalRequest $request): array
    {
        $snapshot = $request->policy_snapshot;

        return [
            'id' => (int) $request->id,
            'status' => $request->status,
            'payload_hash' => $request->payload_hash,
            'target_uuid' => $snapshot['payload']['target_uuid'] ?? null,
            'version_number' => $snapshot['payload']['version_number'] ?? null,
            'required_approvals' => (int) $request->required_approvals,
            'approvals_received' => $request->decisions->where('decision', 'APPROVE')->count(),
            'requested_by_user_id' => $request->requested_by_user_id,
            'expires_at' => $request->expires_at?->toIso8601String(),
            'decisions' => $request->decisions->map(fn ($decision) => [
                'reviewer_user_id' => (int) $decision->approver_user_id,
                'decision' => $decision->decision,
                'comment' => $decision->comment,
            ])->values()->all(),
        ];
    }

    private function verifyMedia(Organisation $org, ContentTarget $target): void
    {
        foreach ($target->version->mediaLinks as $link) {
            if (! $link->asset || ! $link->asset->storage_media_id || ! $link->asset->sha256) {
                throw new ConflictHttpException('Content approval requires verifiable canonical media digests.');
            }
            $canonical = $this->media->inspectCanonical($org, (int) $link->asset->storage_media_id);
            if (! $canonical['sha256'] || ! hash_equals((string) $link->asset->sha256, (string) $canonical['sha256'])) {
                throw new ConflictHttpException('Canonical media has changed. A new content draft is required.');
            }
        }
    }

    private function verifyTarget(Organisation $org, Brand $brand, ContentItem $item, ContentTarget $target): void
    {
        if ($item->state !== 'DRAFT') {
            throw new ConflictHttpException('Only draft content can enter review.');
        }
        if ((int) $target->version->version_number !== $this->studio->latestVersionNumber($org, $brand, $item)) {
            throw new ConflictHttpException('This target references an outdated version. Create a new target for the latest version.');
        }
        if ($target->account->status !== SocialAccountStatus::ACTIVE->value) {
            throw new ConflictHttpException('The connected social account is no longer active.');
        }
        $this->verifyMedia($org, $target);
    }

    /** For ambiguous AI/sensitive flags, take the strictest matching publish rules; never assume false. */
    private function policyRequirements(Organisation $org, Brand $brand, ContentItem $item, ContentTarget $target, BrandApprovalPolicy $policy): array
    {
        $required = max(1, (int) $policy->default_required_approvals);
        $forbidSelf = (bool) $policy->forbid_self_approval;
        $rules = [];
        foreach ($this->approvals->publishingRules($org, $brand, $policy) as $rule) {
            if ($rule->platform !== null && strcasecmp($rule->platform, $target->account->provider) !== 0) {
                continue;
            }
            if ($rule->content_kind !== null && $rule->content_kind !== $item->content_kind) {
                continue;
            }
            if (! RiskRuleMatcher::matches(
                $rule->ai_generated === null ? null : (bool) $rule->ai_generated,
                $rule->includes_sensitive_claim === null ? null : (bool) $rule->includes_sensitive_claim,
                $target->version->ai_generated,
                $target->version->includes_sensitive_claim,
            )) {
                continue;
            }
            $required = max($required, (int) ($rule->required_approvals ?? 1));
            $forbidSelf = $forbidSelf || (bool) $rule->forbid_self_approval;
            $rules[] = [
                'uuid' => (string) $rule->uuid, 'required_approvals' => (int) ($rule->required_approvals ?? 1),
                'requires_human_approval' => (bool) $rule->requires_human_approval,
                'forbid_self_approval' => (bool) $rule->forbid_self_approval,
                'eligible_reviewer_role_ids' => $this->approvals->reviewerRoleIds($org, $brand, $rule),
            ];
        }

        return [$required, $forbidSelf, $rules];
    }

    /** Shared, side-effect-free reconstruction for downstream publication gates. */
    public function currentSnapshot(Organisation $org, Brand $brand, ContentItem $item, ContentTarget $target, BrandApprovalPolicy $policy): array
    {
        [$required, $forbidSelf, $rules] = $this->policyRequirements($org, $brand, $item, $target, $policy);
        $snapshot = ApprovalPayloadSnapshot::capture($item, $target, $policy, $rules);

        return ['payload_hash' => ApprovalPayloadSnapshot::hash($snapshot),
            'required_approvals' => $required, 'forbid_self_approval' => $forbidSelf];
    }

    public function submit(User $actor, Organisation $org, Brand $brand, string $itemUuid, string $targetUuid, string $expectedTargetHash): array
    {
        return $this->write($actor, $org, $brand, 'approval_request.store', function () use ($actor, $org, $brand, $itemUuid, $targetUuid, $expectedTargetHash) {
            $item = $this->studio->findItem($org, $brand, $itemUuid, true);
            $target = $this->approvals->target($org, $brand, $item, $targetUuid);
            if (! hash_equals((string) $target->payload_hash, $expectedTargetHash)) {
                throw new ConflictHttpException('The platform draft has changed. Reload before requesting approval.');
            }
            $this->verifyTarget($org, $brand, $item, $target);
            $policy = $this->approvals->activePolicy($org, $brand);
            if (! $policy) {
                throw new ConflictHttpException('Activate a brand approval policy before requesting publication approval.');
            }
            $pending = $this->approvals->pending($org, $brand, $target);
            if ($pending && $pending->expires_at?->isPast()) {
                $this->approvals->resolve($pending, 'EXPIRED');
                $pending = null;
            }
            if ($pending) {
                throw new ConflictHttpException('A pending review already exists for this exact target.');
            }

            [$required, $forbidSelf, $rules] = $this->policyRequirements($org, $brand, $item, $target, $policy);
            if ($required > 10) {
                throw new ConflictHttpException('Approval policy exceeds supported reviewer quorum.');
            }
            $snapshot = ApprovalPayloadSnapshot::capture($item, $target, $policy, $rules);
            $request = $this->approvals->create($org, $brand, $target, $policy, $actor,
                ApprovalPayloadSnapshot::hash($snapshot), $snapshot, $required, $forbidSelf);
            $this->audit->record($org, $actor, 'content.approval.submitted', 'approval_request', (string) $request->id,
                $brand, ['payload_hash' => $request->payload_hash, 'policy_version' => $policy->policy_version]);

            return ['id' => (int) $request->id, 'status' => 'PENDING', 'payload_hash' => $request->payload_hash,
                'required_approvals' => $required, 'expires_at' => $request->expires_at?->toIso8601String()];
        });
    }

    public function decide(User $actor, Organisation $org, Brand $brand, string $itemUuid, int $requestId, string $decision, string $comment): array
    {
        return $this->write($actor, $org, $brand, 'content.approve', function () use ($actor, $org, $brand, $itemUuid, $requestId, $decision, $comment) {
            $item = $this->studio->findItem($org, $brand, $itemUuid, true);
            $request = $this->approvals->find($org, $brand, $item, $requestId, true);
            if ($request->status !== 'PENDING') {
                throw new ConflictHttpException('This approval is already resolved.');
            }
            if ($request->expires_at?->isPast()) {
                $this->approvals->resolve($request, 'EXPIRED');

                return ['id' => $requestId, 'status' => 'EXPIRED'];
            }
            $policy = $this->approvals->activePolicy($org, $brand);
            if (! $policy || (int) $policy->id !== (int) $request->approval_policy_id) {
                throw new ConflictHttpException('The active approval policy changed. Re-submit under the current policy.');
            }
            $target = $this->approvals->targetForRequest($org, $brand, $item, $request);
            $this->verifyTarget($org, $brand, $item, $target);
            [$currentRequired, $currentNoSelf, $ruleSnapshot] = $this->policyRequirements($org, $brand, $item, $target, $policy);
            $fresh = ApprovalPayloadSnapshot::capture($item, $target, $policy, $ruleSnapshot);
            if ($currentRequired !== (int) $request->required_approvals
                || $currentNoSelf !== (bool) ($request->policy_snapshot['forbid_self_approval'] ?? true)) {
                throw new ConflictHttpException('Approval policy requirements changed; re-submit review.');
            }
            if (! hash_equals($request->payload_hash, ApprovalPayloadSnapshot::hash($fresh))) {
                throw new ConflictHttpException('The evidence hash changed; a new approval is required.');
            }
            $snapshot = $request->policy_snapshot;
            if (($snapshot['forbid_self_approval'] ?? true)
                && ((int) $request->requested_by_user_id === (int) $actor->id
                    || (int) $item->created_by_user_id === (int) $actor->id
                    || (int) $target->version->created_by_user_id === (int) $actor->id)) {
                throw new AuthorizationException('Authors and submitters may not approve their own work.');
            }
            $brandRoleId = $this->approvals->currentBrandRoleId($org, $brand, $actor);
            foreach ($ruleSnapshot as $rule) {
                $allowed = $rule['eligible_reviewer_role_ids'];
                if ($allowed !== [] && ($brandRoleId === null || ! in_array($brandRoleId, $allowed, true))) {
                    throw new AuthorizationException('The approval policy does not permit your reviewer role.');
                }
            }
            if ($this->approvals->hasDecision($request, $actor)) {
                throw new ConflictHttpException('Each reviewer may decide only once.');
            }
            if (! in_array($decision, ['APPROVE', 'REJECT'], true)) {
                throw new \InvalidArgumentException('Invalid approval decision.');
            }
            $this->approvals->decide($org, $brand, $request, $actor, $decision, $comment);
            if ($decision === 'REJECT') {
                $this->approvals->resolve($request, 'REJECTED');
            } elseif ($this->approvals->countApprovals($request) >= $request->required_approvals) {
                $this->approvals->resolve($request, 'APPROVED');
            }
            $this->audit->record($org, $actor, 'content.approval.decided', 'approval_request', (string) $request->id,
                $brand, ['decision' => $decision, 'status' => $request->status, 'payload_hash' => $request->payload_hash]);

            return ['id' => $requestId, 'status' => $request->status,
                'approvals_received' => $this->approvals->countApprovals($request), 'required_approvals' => $request->required_approvals];
        });
    }

    public function withdraw(User $actor, Organisation $org, Brand $brand, string $itemUuid, int $requestId): void
    {
        $this->write($actor, $org, $brand, 'approval_request.update', function () use ($actor, $org, $brand, $itemUuid, $requestId) {
            $item = $this->studio->findItem($org, $brand, $itemUuid, true);
            $request = $this->approvals->find($org, $brand, $item, $requestId, true);
            if ((int) $request->requested_by_user_id !== (int) $actor->id) {
                throw new AuthorizationException('Only the original submitter can withdraw this review.');
            }
            if ($request->status !== 'PENDING') {
                throw new ConflictHttpException('Only pending reviews may be withdrawn.');
            }
            $this->approvals->resolve($request, 'WITHDRAWN');
            $this->audit->record($org, $actor, 'content.approval.withdrawn', 'approval_request', (string) $request->id, $brand);
        });
    }
}
