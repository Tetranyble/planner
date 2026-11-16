<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

final class LinkStorageMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['storage_media_id' => ['required', 'integer', 'min:1']];
    }
}
