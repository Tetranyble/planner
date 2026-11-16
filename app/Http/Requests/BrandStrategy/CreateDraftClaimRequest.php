<?php

namespace App\Http\Requests\BrandStrategy;

use App\Enums\ClaimCategory;
use Illuminate\Validation\Rule;

final class CreateDraftClaimRequest extends StrategyRequest
{
    public function rules(): array
    {
        return [
            'claim_text' => ['required', 'string', 'min:5', 'max:5000'],
            'claim_category' => ['required', Rule::enum(ClaimCategory::class)],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
        ];
    }
}
