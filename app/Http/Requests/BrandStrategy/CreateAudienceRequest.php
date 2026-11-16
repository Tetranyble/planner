<?php

namespace App\Http\Requests\BrandStrategy;

use App\Enums\AudienceKind;
use App\Enums\DecisionRole;
use Illuminate\Validation\Rule;

final class CreateAudienceRequest extends StrategyRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'audience_kind' => ['required', Rule::enum(AudienceKind::class)],
            'priority' => ['sometimes', 'integer', 'between:1,100'],
            'roles' => ['sometimes', 'array', 'max:20'],
            'roles.*.role' => ['required', 'string', 'max:120'],
            'roles.*.decision_role' => ['required', Rule::enum(DecisionRole::class)],
            'pain_points' => ['sometimes', 'array', 'max:20'],
            'pain_points.*.description' => ['required', 'string', 'max:255'],
            'pain_points.*.priority' => ['sometimes', 'integer', 'between:1,100'],
            'goals' => ['sometimes', 'array', 'max:20'],
            'goals.*.description' => ['required', 'string', 'max:255'],
            'goals.*.priority' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $roles = $this->input('roles', []);
            if (! is_array($roles)) {
                return;
            }
            $seen = [];
            foreach ($roles as $idx => $role) {
                if (! is_array($role) || ! is_string($role['role'] ?? null)) {
                    continue;
                }
                $key = mb_strtolower(trim($role['role'])).'|'.($role['decision_role'] ?? '');
                if (isset($seen[$key])) {
                    $validator->errors()->add("roles.$idx.role", 'Duplicate audience role.');
                }
                $seen[$key] = true;
            }
        });
    }
}
