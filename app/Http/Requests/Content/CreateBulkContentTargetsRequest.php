<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

final class CreateBulkContentTargetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'version_uuid' => ['required', 'uuid'],
            'variants' => ['required', 'array', 'min:1', 'max:20'],
            'variants.*.social_account_uuid' => ['required', 'uuid', 'distinct'],
            'variants.*.platform_caption' => ['required', 'string', 'max:5000'],
        ];
    }
}
