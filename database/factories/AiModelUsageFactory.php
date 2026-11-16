<?php

namespace Database\Factories;

use App\Models\AiModelUsage;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiModelUsage>
 */
class AiModelUsageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invocation_key' => 'fixture-'.Str::random(16),
            'model_provider' => 'openai',
            'model_name' => 'test-model',
            'recorded_at' => now(),
        ];
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
