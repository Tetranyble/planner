<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

final class CreateContentTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'version_uuid' => ['required', 'uuid'],
            'social_account_uuid' => ['required', 'uuid'],
            'platform_caption' => ['required', 'string', 'max:5000'],
            'publishing_mode' => ['sometimes', 'in:ASSISTED,AUTOMATIC'],
        ];
    }
}
