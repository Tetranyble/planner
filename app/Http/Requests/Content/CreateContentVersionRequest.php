<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

final class CreateContentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'copy_text' => ['required', 'string', 'max:50000'],
            'ai_generated' => ['nullable', 'boolean'],
            'includes_sensitive_claim' => ['nullable', 'boolean'],
            'media_uuids' => ['sometimes', 'array', 'max:12'],
            'media_uuids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
