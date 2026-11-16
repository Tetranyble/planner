<?php

namespace App\Http\Requests\BrandStrategy;

final class UpdateBrandProfileRequest extends StrategyRequest
{
    public function rules(): array
    {
        return [
            'positioning' => ['required', 'string', 'max:255'],
            'default_locale' => ['required', 'string', 'max:30', 'regex:/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/'],
        ];
    }
}
