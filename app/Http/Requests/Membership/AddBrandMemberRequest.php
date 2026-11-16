<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AddBrandMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
            'role' => ['required', Rule::in(['owner', 'manager', 'contributor', 'viewer'])],
        ];
    }
}
