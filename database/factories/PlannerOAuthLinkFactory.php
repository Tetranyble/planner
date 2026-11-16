<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\PlannerAgentDelegation;
use App\Models\PlannerOAuthLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlannerOAuthLink> */
final class PlannerOAuthLinkFactory extends Factory
{
    protected $model = PlannerOAuthLink::class;

    public function forDelegation(PlannerAgentDelegation $delegation): static
    {
        return $this->state(fn (): array => [
            'user_id' => $delegation->user_id,
            'organisation_id' => $delegation->organisation_id,
            'brand_id' => $delegation->brand_id,
            'delegation_id' => $delegation->id,
        ]);
    }

    public function definition(): array
    {
        return [
            'oauth_client_id' => (string) $this->faker->uuid(),
            'user_id' => User::factory(),
            'organisation_id' => Organisation::factory(),
            // Specify an existing tenant-compatible brand and delegation in integration fixtures.
            'brand_id' => null,
            'delegation_id' => null,
        ];
    }
}
