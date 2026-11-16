<?php

namespace Database\Factories;

use App\Models\NotificationPreference;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notification_kind' => 'TEST',
        ];
    }

    public function forOrganisation(Organisation $organisation): static
    {
        return $this->state(fn (): array => ['organisation_id' => $organisation->id]);
    }
}
