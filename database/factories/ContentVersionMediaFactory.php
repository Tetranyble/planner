<?php

namespace Database\Factories;

use App\Models\ContentVersion;
use App\Models\ContentVersionMedia;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentVersionMedia> */
final class ContentVersionMediaFactory extends Factory
{
    protected $model = ContentVersionMedia::class;

    /** Scoped foreign keys are intentionally required via forBrand() or a more specific factory state. */
    public function definition(): array
    {
        return ['sort_order' => 0, 'role' => 'PRIMARY'];
    }

    public function forVersionAndAsset(ContentVersion $version, MediaAsset $asset): static
    {
        if ((int) $version->organisation_id !== (int) $asset->organisation_id || (int) $version->brand_id !== (int) $asset->brand_id) {
            throw new \InvalidArgumentException('Version and media must be in the same brand.');
        }

        return $this->state(fn () => ['organisation_id' => $version->organisation_id, 'brand_id' => $version->brand_id,
            'content_version_id' => $version->id, 'media_asset_id' => $asset->id]);
    }
}
