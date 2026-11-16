<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\PublicationAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PublicationAttempt>
 */
class PublicationAttemptFactory extends Factory
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
            'attempt_number' => 1,
            'operation_key' => 'fixture-'.Str::random(16),
            'started_at' => now(),
            'response_redacted' => [],
        ];
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
