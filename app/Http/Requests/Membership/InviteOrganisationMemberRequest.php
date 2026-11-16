<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InviteOrganisationMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:254'],
            'role' => ['required', Rule::in(['owner', 'manager', 'contributor', 'viewer'])],
        ];
    }
}
