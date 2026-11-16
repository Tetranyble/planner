<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\MediaPublicationRun;
use App\Models\MediaTikTokConsent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaTikTokConsent> */
final class MediaTikTokConsentFactory extends Factory
{
    protected $model = MediaTikTokConsent::class;

    public function definition(): array
    {
        return ['approval_payload_hash' => str_repeat('a', 64), 'media_sha256' => str_repeat('b', 64),
            'grant_fingerprint' => str_repeat('c', 64),
            'privacy_selection' => ['privacy_level' => 'SELF_ONLY', 'disable_comment' => true,
                'disable_duet' => true, 'disable_stitch' => true, 'is_aigc' => false, 'explicit_consent' => true],
            'verified_creator_options' => ['privacy_levels' => ['SELF_ONLY'],
                'comment_disabled' => true, 'duet_disabled' => true, 'stitch_disabled' => true],
            'verified_at' => now()];
    }

    public function forRun(Brand $brand, MediaPublicationRun $run, User $actor): static
    {
        if ((int) $run->organisation_id !== (int) $brand->organisation_id || (int) $run->brand_id !== (int) $brand->id) {
            throw new \InvalidArgumentException('Creator consent run scope mismatch.');
        }

        return $this->state(fn () => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id,
            'media_publication_run_id' => $run->id, 'consenting_user_id' => $actor->id]);
    }
}
