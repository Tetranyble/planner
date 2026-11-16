<?php

namespace App\Http\Requests\BrandStrategy;

use App\Enums\VoiceEnforcement;
use App\Enums\VoiceExampleKind;
use App\Enums\VoiceRuleKind;
use App\Enums\VoiceTermUsage;
use Illuminate\Validation\Rule;

final class CreateVoiceRequest extends StrategyRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'locale' => ['required', 'string', 'max:30', 'regex:/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/'],
            'tone_description' => ['nullable', 'string', 'max:3000'],
            'formality' => ['nullable', 'integer', 'between:1,5'],
            'warmth' => ['nullable', 'integer', 'between:1,5'],
            'directness' => ['nullable', 'integer', 'between:1,5'],
            'is_default' => ['required', 'boolean'],
            'rules' => ['sometimes', 'array', 'max:30'],
            'rules.*.rule_kind' => ['required', Rule::enum(VoiceRuleKind::class)],
            'rules.*.instruction' => ['required', 'string', 'max:3000'],
            'rules.*.enforcement' => ['sometimes', Rule::enum(VoiceEnforcement::class)],
            'rules.*.priority' => ['sometimes', 'integer', 'between:1,100'],
            'terms' => ['sometimes', 'array', 'max:30'],
            'terms.*.term' => ['required', 'string', 'max:255'],
            'terms.*.usage' => ['required', Rule::enum(VoiceTermUsage::class)],
            'terms.*.replacement_term' => ['nullable', 'string', 'max:255'],
            'terms.*.rationale' => ['nullable', 'string', 'max:2000'],
            'examples' => ['sometimes', 'array', 'max:20'],
            'examples.*.example_kind' => ['required', Rule::enum(VoiceExampleKind::class)],
            'examples.*.example_text' => ['required', 'string', 'max:255'],
            'examples.*.explanation' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $seen = [];
            foreach ((array) $this->input('terms', []) as $i => $term) {
                if (! is_array($term) || ! is_string($term['term'] ?? null)) {
                    continue;
                }
                $key = mb_strtolower(trim($term['term'])).'|'.($term['usage'] ?? '');
                if (isset($seen[$key])) {
                    $validator->errors()->add("terms.$i.term", 'Duplicate usage of this term.');
                }
                $seen[$key] = true;
                if (($term['usage'] ?? '') === 'REPLACE' && empty($term['replacement_term'])) {
                    $validator->errors()->add("terms.$i.replacement_term", 'Replacement is required for REPLACE.');
                }
            }
        });
    }
}
