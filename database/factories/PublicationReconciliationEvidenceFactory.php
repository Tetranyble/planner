<?php

namespace Database\Factories;

use App\Models\PublicationReconciliationEvidence;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PublicationReconciliationEvidence> */
final class PublicationReconciliationEvidenceFactory extends Factory
{
    protected $model = PublicationReconciliationEvidence::class;

    public function definition(): array
    {
        return ['organisation_id' => null, 'brand_id' => null, 'publication_job_id' => null];
    }
}
