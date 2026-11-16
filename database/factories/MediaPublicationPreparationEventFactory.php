<?php

namespace Database\Factories;

use App\Models\MediaPublicationPreparation;
use App\Models\MediaPublicationPreparationEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaPublicationPreparationEvent> */
final class MediaPublicationPreparationEventFactory extends Factory
{
    protected $model = MediaPublicationPreparationEvent::class;

    /**
     * Factory defaults are safe fixture values. Scoped foreign keys are supplied
     * by the explicit parent state so database composite FK constraints hold.
     */
    public function definition(): array
    {
        return ['action' => 'CREATED', 'to_state' => 'PREPARED', 'details' => []];
    }

    public function forPreparation(MediaPublicationPreparation $preparation): static
    {
        return $this->state(fn (): array => ['organisation_id' => $preparation->organisation_id, 'brand_id' => $preparation->brand_id, 'media_publication_preparation_id' => $preparation->id]);
    }
}
