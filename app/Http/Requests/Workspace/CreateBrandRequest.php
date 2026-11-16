<?php

namespace App\Http\Requests\Workspace;

use App\Enums\TimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The service enforces organisation scope + Kinship brand.store permission.
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $updates = [];
        if (is_string($this->input('name'))) {
            $updates['name'] = trim($this->input('name'));
        }
        if (is_string($this->input('website_url'))) {
            $updates['website_url'] = trim($this->input('website_url'));
        }
        if ($updates !== []) {
            $this->merge($updates);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'website_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'timezone' => ['sometimes', 'nullable', Rule::enum(TimeZone::class)],
        ];
    }
}
