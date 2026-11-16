<?php

namespace Database\Factories;

use App\Enums\StatusEnum;
use App\Enums\TimeZone;
use App\Models\Brand;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) str()->uuid(),
            'organisation_id' => Organisation::factory(),
            'name' => $this->faker->unique()->company(),
            'website_url' => $this->faker->url(),
            'timezone' => TimeZone::AFRICA_LAGOS->value,
            'status' => StatusEnum::ACTIVE->value,
            'settings' => null,
        ];
    }

    // ---------- States ----------

    public function forOrganisation(Organisation|int $org): static
    {
        return $this->state(fn () => [
            'organisation_id' => $org instanceof Organisation ? $org->id : $org,
        ]);
    }

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

    public function withSettings(array $settings): static
    {
        return $this->state(fn () => ['settings' => $settings]);
    }

    public function withoutWebsite(): static
    {
        return $this->state(fn () => ['website_url' => null]);
    }
}
