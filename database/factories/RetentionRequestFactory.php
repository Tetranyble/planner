<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\RetentionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RetentionRequest>
 */
class RetentionRequestFactory extends Factory
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
            'subject_kind' => 'TEST',
            'subject_ref' => 'TEST',
            'requested_at' => now(),
        ];
    }

    public function forOrganisation(Organisation $organisation): static
    {
        return $this->state(fn (): array => ['organisation_id' => $organisation->id]);
    }
}
