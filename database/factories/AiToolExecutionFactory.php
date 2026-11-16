<?php

namespace Database\Factories;

use App\Models\AiToolExecution;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiToolExecution>
 */
class AiToolExecutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tool_name' => 'TEST',
            'operation_key' => 'fixture-'.Str::random(16),
            'input_hash' => str_repeat('a', 64),
        ];
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
