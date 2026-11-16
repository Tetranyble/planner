<?php

namespace Tests\Unit\Domain\BrandStrategy;

use App\Domain\BrandStrategy\ApprovalPolicyActivationRules;
use PHPUnit\Framework\TestCase;

final class ApprovalPolicyActivationRulesTest extends TestCase
{
    public function test_activation_rejects_missing_publish_rules_or_dangerous_approval_bypasses(): void
    {
        $rules = new ApprovalPolicyActivationRules;
        $policy = ['state' => 'DRAFT', 'external_publishing_requires_approval' => true,
            'forbid_self_approval' => true, 'default_required_approvals' => 1];
        self::assertNotEmpty($rules->violations($policy, []));
        self::assertNotEmpty($rules->violations($policy, [[
            'action_kind' => 'PUBLISH', 'is_enabled' => true,
            'requires_human_approval' => false, 'forbid_self_approval' => false, 'required_approvals' => 0,
        ]]));
        self::assertNotEmpty($rules->violations([...$policy, 'state' => 'ACTIVE'], [[
            'action_kind' => 'PUBLISH', 'is_enabled' => true, 'requires_human_approval' => true,
            'forbid_self_approval' => true, 'required_approvals' => 1,
        ]]));
    }

    public function test_valid_draft_with_mandatory_human_review_can_be_activated(): void
    {
        self::assertSame([], (new ApprovalPolicyActivationRules)->violations([
            'state' => 'DRAFT', 'external_publishing_requires_approval' => true,
            'forbid_self_approval' => true, 'default_required_approvals' => 2,
        ], [[
            'action_kind' => 'PUBLISH', 'is_enabled' => true, 'requires_human_approval' => true,
            'forbid_self_approval' => true, 'required_approvals' => 2,
        ]]));
    }
}
