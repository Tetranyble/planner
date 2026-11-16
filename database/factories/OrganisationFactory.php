<?php

namespace Database\Factories;

use App\Enums\StatusEnum;
use App\Enums\TimeZone;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) str()->uuid(),
            'name' => $this->faker->unique()->company(),
            'timezone' => TimeZone::AFRICA_LAGOS->value,
            'status' => StatusEnum::ACTIVE->value,
        ];
    }

    // ---------- States ----------

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => StatusEnum::SUSPENDED->value]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => StatusEnum::CLOSED->value]);
    }

    public function inTimezone(TimeZone $timezone): static
    {
        return $this->state(fn () => ['timezone' => $timezone->value]);
    }
}
