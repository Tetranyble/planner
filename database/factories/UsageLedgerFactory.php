<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\UsageLedger;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UsageLedger>
 */
class UsageLedgerFactory extends Factory
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
            'usage_kind' => 'TEST',
            'quantity' => 1,
            'unit' => 'TEST',
            'source_ref' => 'TEST',
            'recorded_at' => now(),
        ];
    }

    public function forOrganisation(Organisation $organisation): static
    {
        return $this->state(fn (): array => ['organisation_id' => $organisation->id]);
    }
}
