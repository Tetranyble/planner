<?php

namespace Database\Factories;

use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\MediaPublicationPreparation;
use App\Models\PublicationJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaPublicationPreparation> */
final class MediaPublicationPreparationFactory extends Factory
{
    protected $model = MediaPublicationPreparation::class;

    public function definition(): array
    {
        // Scope and FKs are mandatory from forEvidence(), not constructed independently.
        return [
            'uuid' => (string) $this->faker->uuid(),
            'provider' => 'instagram', 'protocol' => 'IMAGE_CONTAINER', 'state' => 'PREPARED',
            'media_sha256' => str_repeat('a', 64), 'approval_payload_hash' => str_repeat('b', 64),
            'protocol_manifest' => ['mode' => 'IMAGE_CONTAINER', 'stages' => ['CREATE_CONTAINER']],
            'version' => 1, 'blocker_code' => 'PROVIDER_MEDIA_DELIVERY_NOT_RELEASED',
        ];
    }

    public function forEvidence(Brand $brand, PublicationJob $job, MediaAsset $asset,
        ApprovalRequest $approval, User $actor): static
    {
        if ((int) $brand->organisation_id !== (int) $job->organisation_id
            || (int) $brand->id !== (int) $job->brand_id
            || (int) $asset->organisation_id !== (int) $job->organisation_id
            || (int) $asset->brand_id !== (int) $job->brand_id
            || (int) $approval->id !== (int) $job->approval_request_id) {
            throw new \InvalidArgumentException('Preparation evidence must share the target brand and job.');
        }

        return $this->state(fn () => [
            'organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id,
            'publication_job_id' => $job->id, 'media_asset_id' => $asset->id,
            'prepared_by_user_id' => $actor->id, 'media_sha256' => $asset->sha256,
            'approval_payload_hash' => $approval->payload_hash,
        ]);
    }
}
