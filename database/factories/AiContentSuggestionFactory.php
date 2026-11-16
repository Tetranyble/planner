<?php

namespace Database\Factories;

use App\Models\AiContentSuggestion;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AiContentSuggestion> */
final class AiContentSuggestionFactory extends Factory
{
    protected $model = AiContentSuggestion::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'organisation_id' => Organisation::factory(),
            'brand_id' => fn (array $a) => Brand::factory()->create(['organisation_id' => $a['organisation_id']])->id,
            'requested_by_user_id' => User::factory(),
            'topic' => 'A launch announcement', 'platform' => 'linkedin', 'model' => 'test-fixture',
            'prompt_hash' => hash('sha256', 'test prompt'), 'result_hash' => hash('sha256', 'test result'),
            'suggestion_text' => 'Draft only. Verify this content before publication.',
            'status' => 'SUGGESTED',
        ];
    }
}
