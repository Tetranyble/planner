<?php

namespace Database\Factories;

use App\Enums\StateEnum;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Tetranyble\Kinship\Models\Role;

/** @extends Factory<OrganisationMembership> */
final class OrganisationMembershipFactory extends Factory
{
    protected $model = OrganisationMembership::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'user_id' => User::factory(),
            'role_id' => function (array $attributes): int {
                $org = Organisation::query()->findOrFail($attributes['organisation_id']);

                return (int) Role::factory()->forWorkspace($org)->create()->getKey();
            },
            'state' => StateEnum::ACTIVE->value,
            'joined_at' => now(),
        ];
    }

    public function forOrganisation(Organisation $organisation): static
    {
        return $this->state(fn (): array => ['organisation_id' => $organisation->getKey()]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->getKey()]);
    }

    public function withRole(Role $role): static
    {
        return $this->state(fn (): array => ['role_id' => $role->getKey()]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['state' => StateEnum::SUSPENDED->value]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['state' => StateEnum::REVOKED->value]);
    }
}
