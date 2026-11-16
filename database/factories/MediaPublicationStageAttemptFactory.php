<?php

namespace Database\Factories;

use App\Models\MediaPublicationRun;
use App\Models\MediaPublicationStageAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaPublicationStageAttempt> */
final class MediaPublicationStageAttemptFactory extends Factory
{
    protected $model = MediaPublicationStageAttempt::class;

    /**
     * Factory defaults are safe fixture values. Scoped foreign keys are supplied
     * by the explicit parent state so database composite FK constraints hold.
     */
    public function definition(): array
    {
        return ['stage_sequence' => 1, 'operation' => 'CREATE_CONTAINER', 'state' => 'STARTED'];
    }

    public function forRun(MediaPublicationRun $run): static
    {
        return $this->state(fn (): array => ['organisation_id' => $run->organisation_id, 'brand_id' => $run->brand_id, 'media_publication_run_id' => $run->id]);
    }
}
