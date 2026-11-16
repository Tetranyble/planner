<?php

namespace App\Http\Requests\Workspace;

use App\Enums\TimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateOrganisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is established server-side by the onboarding service.
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'timezone' => ['sometimes', 'required', Rule::enum(TimeZone::class)],
        ];
    }
}
