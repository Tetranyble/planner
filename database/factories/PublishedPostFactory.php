<?php

namespace Database\Factories;

use App\Models\PublicationJob;
use App\Models\PublishedPost;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PublishedPost> */
final class PublishedPostFactory extends Factory
{
    protected $model = PublishedPost::class;

    /** Never invent cross-tenant foreign keys. Use forJobAndAccount(). */
    public function definition(): array
    {
        return ['external_post_id' => 'Xy123AbC_-1', 'provider' => 'youtube',
            'remote_status' => 'PUBLISHED', 'published_at' => now()];
    }

    public function forJobAndAccount(PublicationJob $job, SocialAccount $account): static
    {
        if ((int) $job->organisation_id !== (int) $account->organisation_id
            || (int) $job->brand_id !== (int) $account->brand_id) {
            throw new \InvalidArgumentException('Published post fixture must remain in the same brand.');
        }

        return $this->state(fn () => ['organisation_id' => $job->organisation_id,
            'brand_id' => $job->brand_id, 'publication_job_id' => $job->id,
            'social_account_id' => $account->id, 'provider' => $account->provider]);
    }
}
