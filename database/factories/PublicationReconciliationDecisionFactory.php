<?php

namespace Database\Factories;

use App\Models\PublicationReconciliationDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PublicationReconciliationDecision> */
final class PublicationReconciliationDecisionFactory extends Factory
{
    protected $model = PublicationReconciliationDecision::class;

    public function definition(): array
    {
        return ['organisation_id' => null, 'brand_id' => null, 'publication_job_id' => null];
    }
}
