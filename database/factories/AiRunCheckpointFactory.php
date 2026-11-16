<?php

namespace Database\Factories;

use App\Models\AiRunCheckpoint;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiRunCheckpoint>
 */
class AiRunCheckpointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'sequence_number' => 1,
            'checkpoint_kind' => 'test',
            'state_ref' => 'TEST',
            'state_hash' => str_repeat('a', 64),
            'recorded_at' => now(),
        ];
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
