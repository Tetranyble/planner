<?php

namespace App\Http\Requests\Social;

use Illuminate\Foundation\Http\FormRequest;

final class PrepareGrantRevocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:12', 'max:250']];
    }
}
