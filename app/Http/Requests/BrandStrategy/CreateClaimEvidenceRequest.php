<?php

namespace App\Http\Requests\BrandStrategy;

final class CreateClaimEvidenceRequest extends StrategyRequest
{
    public function rules(): array
    {
        return [
            'source_uuid' => ['required', 'uuid'],
            'supporting_excerpt' => ['required', 'string', 'min:10', 'max:10000'],
            'source_locator' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
