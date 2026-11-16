<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebhookEvent> */
final class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        return ['provider' => 'facebook', 'external_event_id' => 'fixture-'.str()->uuid(),
            'verified' => true, 'received_at' => now(), 'state' => 'PENDING',
            'redacted_payload' => json_encode(['kind' => 'synthetic'], JSON_THROW_ON_ERROR)];
    }

    public function forAccount(SocialAccount $account): static
    {
        return $this->state(fn () => ['organisation_id' => $account->organisation_id,
            'social_account_id' => $account->id, 'provider' => $account->provider]);
    }
}
