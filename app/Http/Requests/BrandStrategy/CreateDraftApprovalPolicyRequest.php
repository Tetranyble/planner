<?php

namespace App\Http\Requests\BrandStrategy;

use App\Enums\ApprovalActionKind;
use App\Enums\ContentKind;
use Illuminate\Validation\Rule;

final class CreateDraftApprovalPolicyRequest extends StrategyRequest
{
    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:2000'],
            'default_required_approvals' => ['required', 'integer', 'between:1,10'],
            'rules' => ['sometimes', 'array', 'max:50'],
            'rules.*.rule_name' => ['required', 'string', 'max:255'],
            'rules.*.priority' => ['required', 'integer', 'between:1,65535', 'distinct'],
            'rules.*.action_kind' => ['required', Rule::enum(ApprovalActionKind::class)],
            'rules.*.platform' => ['nullable', 'string', 'max:100'],
            'rules.*.content_kind' => ['nullable', Rule::enum(ContentKind::class)],
            'rules.*.required_approvals' => ['nullable', 'integer', 'between:1,10'],
        ];
    }
}
