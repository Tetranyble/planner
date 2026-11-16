<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DecideContentApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['APPROVE', 'REJECT'])],
            'comment' => ['required', 'string', 'min:8', 'max:2000']];
    }
}
