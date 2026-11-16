<?php

namespace Database\Factories;

use App\Models\ProviderGrantGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProviderGrantGroup> */
final class ProviderGrantGroupFactory extends Factory
{
    protected $model = ProviderGrantGroup::class;

    public function definition(): array
    {
        return [
            'provider_family' => 'meta',
            'application_id' => (string) config('social_integrations.providers.facebook.client_id', 'fixture-app'),
            'actor_digest' => hash('sha256', fake()->uuid()),
        ];
    }
}
