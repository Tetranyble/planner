<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;

final class AcceptOrganisationInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasVerifiedEmail() === true;
    }

    public function rules(): array
    {
        return ['token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']];
    }
}
