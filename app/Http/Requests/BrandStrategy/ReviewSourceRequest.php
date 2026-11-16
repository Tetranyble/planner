<?php

namespace App\Http\Requests\BrandStrategy;

use Illuminate\Validation\Rule;

final class ReviewSourceRequest extends StrategyRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['VERIFY', 'REJECT'])],
            'rationale' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
