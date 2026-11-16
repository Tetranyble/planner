<?php

namespace App\Http\Requests\BrandStrategy;

use Illuminate\Validation\Rule;

final class CreateKnowledgeSourceRequest extends StrategyRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:4', 'max:255'],
            'source_type' => ['required', Rule::in(['MANUAL', 'WEB'])],
            'source_url' => ['nullable', 'required_if:source_type,WEB', 'url:http,https', 'max:255'],
            'content_text' => ['nullable', 'required_if:source_type,MANUAL', 'string', 'max:12000'],
        ];
    }
}
