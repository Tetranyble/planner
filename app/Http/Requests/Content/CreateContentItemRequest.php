<?php

namespace App\Http\Requests\Content;

use App\Enums\ContentKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateContentItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'content_kind' => ['required', Rule::in(array_column(ContentKind::cases(), 'value'))],
            'copy_text' => ['required', 'string', 'max:50000'],
            'ai_generated' => ['nullable', 'boolean'],
            'includes_sensitive_claim' => ['nullable', 'boolean'],
            'media_uuids' => ['sometimes', 'array', 'max:12'],
            'media_uuids.*' => ['required', 'uuid', 'distinct'],
            'campaign_uuid' => ['nullable', 'uuid'],
            'series_uuid' => ['nullable', 'uuid'],
        ];
    }
}
