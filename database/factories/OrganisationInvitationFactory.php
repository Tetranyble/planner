<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\OrganisationInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Tetranyble\Kinship\Models\Role;

/** @extends Factory<OrganisationInvitation> */
final class OrganisationInvitationFactory extends Factory
{
    protected $model = OrganisationInvitation::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'role_id' => function (array $attributes): int {
                $organisation = Organisation::query()->findOrFail($attributes['organisation_id']);

                return (int) Role::factory()->forWorkspace($organisation)->create()->getKey();
            },
            'invited_by_user_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(7),
        ];
    }

    public function forOrganisationAndRole(Organisation $organisation, Role $role): static
    {
        return $this->state(fn (): array => [
            'organisation_id' => $organisation->getKey(), 'role_id' => $role->getKey(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => ['accepted_at' => now()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }
}
