<?php

namespace Database\Factories;

use App\Models\MetaDataDeletionRequest;
use App\Models\MetaDataDeletionTarget;
use App\Models\ProviderConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MetaDataDeletionTarget> */
final class MetaDataDeletionTargetFactory extends Factory
{
    protected $model = MetaDataDeletionTarget::class;

    public function definition(): array
    {
        // A target requires an explicit tenant-scoped grant via forConnection().
        return ['state' => 'PENDING'];
    }

    public function forConnection(ProviderConnection $connection, ?MetaDataDeletionRequest $request = null): static
    {
        return $this->state(fn () => [
            'request_id' => $request?->id ?? MetaDataDeletionRequest::factory(),
            'organisation_id' => $connection->organisation_id,
            'provider_connection_id' => $connection->id,
        ]);
    }
}
