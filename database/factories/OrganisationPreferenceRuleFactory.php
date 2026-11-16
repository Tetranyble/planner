<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Models\Organisation;
use App\Models\OrganisationPreferenceRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Tetranyble\Kinship\Models\Role;

/**
 * @extends Factory<OrganisationPreferenceRule>
 */
class OrganisationPreferenceRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) str()->uuid(),
            'organisation_id' => Organisation::factory(),
            'role_id' => null,
            'event_type' => NotificationEventType::PUBLICATION_FAILED->value,
            'channel' => NotificationChannel::EMAIL->value,
            'enabled' => true,
        ];
    }

    // ---------- States ----------

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }

    public function forEvent(NotificationEventType $event): static
    {
        return $this->state(fn () => ['event_type' => $event->value]);
    }

    public function forChannel(NotificationChannel $channel): static
    {
        return $this->state(fn () => ['channel' => $channel->value]);
    }

    public function forRole(Role|int $role): static
    {
        return $this->state(fn () => [
            'role_id' => $role instanceof Role ? $role->id : $role,
        ]);
    }

    public function forOrganisation(Organisation|int $org): static
    {
        return $this->state(fn () => [
            'organisation_id' => $org instanceof Organisation ? $org->id : $org,
        ]);
    }
}
