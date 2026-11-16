<?php

namespace Database\Factories;

use App\Models\ApiLog;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApiLog> */
final class ApiLogFactory extends Factory
{
    protected $model = ApiLog::class;

    public function definition(): array
    {
        return ['provider' => 'facebook', 'operation' => 'fixture.read',
            'correlation_id' => (string) str()->uuid(), 'duration_ms' => 1,
            'occurred_at' => now()];
    }

    public function forAccount(SocialAccount $account): static
    {
        return $this->state(fn () => [
            'organisation_id' => $account->organisation_id, 'social_account_id' => $account->id,
            'provider' => $account->provider,
        ]);
    }
}
