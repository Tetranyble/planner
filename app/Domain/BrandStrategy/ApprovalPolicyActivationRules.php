<?php

namespace App\Domain\BrandStrategy;

use App\Enums\ApprovalActionKind;
use App\Enums\ApprovalPolicyState;

/** An active version may never permit publishing without human review. */
final class ApprovalPolicyActivationRules
{
    public function violations(array $policy, array $rules): array
    {
        $errors = [];
        if (($policy['state'] ?? '') !== ApprovalPolicyState::DRAFT->value) {
            $errors[] = 'Only a draft policy can be activated.';
        }
        if (! ($policy['external_publishing_requires_approval'] ?? false)) {
            $errors[] = 'External publishing must require approval.';
        }
        if (! ($policy['forbid_self_approval'] ?? false)) {
            $errors[] = 'Self-approval must be prohibited.';
        }
        $required = (int) ($policy['default_required_approvals'] ?? 0);
        if ($required < 1 || $required > 10) {
            $errors[] = 'Required approvals must be between 1 and 10.';
        }
        $publish = 0;
        foreach ($rules as $rule) {
            if (! (bool) ($rule['is_enabled'] ?? false)) {
                continue;
            }
            if (($rule['action_kind'] ?? null) !== ApprovalActionKind::PUBLISH->value) {
                continue;
            }
            $publish++;
            if (! ($rule['requires_human_approval'] ?? false) || ! ($rule['forbid_self_approval'] ?? false)
                || ($rule['required_approvals'] !== null && ((int) $rule['required_approvals'] < 1 || (int) $rule['required_approvals'] > 10))) {
                $errors[] = 'Every publishing rule must require human review and prohibit self-approval.';
            }
        }
        if ($publish === 0) {
            $errors[] = 'At least one enabled publishing rule is required.';
        }

        return $errors;
    }
}
