<?php

namespace Database\Factories;

use App\Domain\Content\ApprovalPayloadSnapshot;
use App\Models\ApprovalRequest;
use App\Models\BrandApprovalPolicy;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApprovalRequest> */
final class ApprovalRequestFactory extends Factory
{
    protected $model = ApprovalRequest::class;

    public function definition(): array
    {
        // Scoped IDs and the authoritative payload hash are supplied via forTarget().
        return ['request_kind' => 'HUMAN', 'status' => 'PENDING', 'required_approvals' => 1,
            'expires_at' => now()->addDays(7), 'policy_snapshot' => [],
        ];
    }

    public function forTarget(ContentTarget $target, BrandApprovalPolicy $policy, User $requester): static
    {
        if ((int) $target->organisation_id !== (int) $policy->organisation_id || (int) $target->brand_id !== (int) $policy->brand_id) {
            throw new \InvalidArgumentException('Approval target and policy must belong to the same brand.');
        }
        $target->loadMissing('version.mediaLinks.asset', 'account');
        $item = ContentItem::query()->where('organisation_id', $target->organisation_id)
            ->where('brand_id', $target->brand_id)->findOrFail($target->version->content_item_id);
        $payload = ApprovalPayloadSnapshot::capture($item, $target, $policy);

        return $this->state(fn () => [
            'organisation_id' => $target->organisation_id, 'brand_id' => $target->brand_id,
            'content_target_id' => $target->id, 'approval_policy_id' => $policy->id,
            'requested_by_user_id' => $requester->id, 'payload_hash' => ApprovalPayloadSnapshot::hash($payload),
            'policy_snapshot' => ['version' => 1, 'payload' => $payload, 'forbid_self_approval' => true, 'required_approvals' => 1],
        ]);
    }
}
