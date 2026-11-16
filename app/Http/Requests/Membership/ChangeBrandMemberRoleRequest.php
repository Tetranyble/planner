<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeBrandMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['role' => ['required', Rule::in(['owner', 'manager', 'contributor', 'viewer'])]];
    }
}
