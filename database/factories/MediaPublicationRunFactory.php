<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\MediaPublicationPreparation;
use App\Models\MediaPublicationRun;
use App\Models\PublicationJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaPublicationRun> */
final class MediaPublicationRunFactory extends Factory
{
    protected $model = MediaPublicationRun::class;

    public function definition(): array
    {
        return ['uuid' => (string) $this->faker->uuid(), 'provider' => 'instagram', 'state' => 'HELD',
            'next_operation' => 'CREATE_CONTAINER', 'version' => 1, 'stage_sequence' => 0, 'poll_count' => 0];
    }

    public function forPreparation(Brand $brand, MediaPublicationPreparation $preparation, PublicationJob $job): static
    {
        if ((int) $brand->organisation_id !== (int) $preparation->organisation_id
            || (int) $brand->id !== (int) $preparation->brand_id
            || (int) $job->id !== (int) $preparation->publication_job_id) {
            throw new \InvalidArgumentException('Run scope must match canonical preparation.');
        }

        return $this->state(fn () => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id,
            'media_publication_preparation_id' => $preparation->id, 'publication_job_id' => $job->id,
            'provider' => $preparation->provider]);
    }
}
