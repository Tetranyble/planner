<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitContentApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['expected_target_hash' => ['required', 'regex:/^[0-9a-f]{64}$/']];
    }
}
