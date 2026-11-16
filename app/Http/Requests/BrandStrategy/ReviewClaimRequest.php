<?php

namespace App\Http\Requests\BrandStrategy;

use Illuminate\Validation\Rule;

final class ReviewClaimRequest extends StrategyRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['APPROVE', 'REJECT'])],
            'rationale' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
