<?php

namespace Database\Factories;

use App\Models\MetaDataDeletionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MetaDataDeletionRequest> */
final class MetaDataDeletionRequestFactory extends Factory
{
    protected $model = MetaDataDeletionRequest::class;

    public function definition(): array
    {
        return [
            'signed_request_digest' => hash('sha256', (string) Str::uuid()),
            'actor_digest' => hash('sha256', 'fixture:'.Str::uuid()),
            'actor_ciphertext' => null,
            'confirmation_code' => Str::random(48),
            'state' => 'PENDING', 'accepted_at' => now(),
            'matched_grants' => 0, 'processed_grants' => 0, 'attempts' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['state' => 'COMPLETED', 'completed_at' => now()]);
    }
}
