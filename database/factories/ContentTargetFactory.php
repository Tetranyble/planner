<?php

namespace Database\Factories;

use App\Models\ContentTarget;
use App\Models\ContentVersion;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentTarget> */
final class ContentTargetFactory extends Factory
{
    protected $model = ContentTarget::class;

    /** Scoped foreign keys are intentionally required via forBrand() or a more specific factory state. */
    public function definition(): array
    {
        return ['platform_caption' => 'Draft platform caption', 'payload_json' => ['schema_version' => 1, 'format' => 'plain_text'],
            'payload_hash' => str_repeat('b', 64), 'publishing_mode' => 'ASSISTED'];
    }

    public function forVersionAndAccount(ContentVersion $version, SocialAccount $account): static
    {
        if ((int) $version->organisation_id !== (int) $account->organisation_id || (int) $version->brand_id !== (int) $account->brand_id) {
            throw new \InvalidArgumentException('Version and account must be in the same brand.');
        }

        return $this->state(fn () => ['organisation_id' => $version->organisation_id, 'brand_id' => $version->brand_id,
            'content_version_id' => $version->id, 'social_account_id' => $account->id]);
    }
}
