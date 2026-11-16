<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaVariant> */
final class MediaVariantFactory extends Factory
{
    protected $model = MediaVariant::class;

    /** Scoped foreign keys are intentionally required via forBrand() or a more specific factory state. */
    public function definition(): array
    {
        return ['variant_key' => 'thumbnail', 'object_key' => 'fixtures/thumbnail.webp',
            'mime_type' => 'image/webp', 'byte_size' => 1200, 'state' => 'READY'];
    }

    public function forAsset(MediaAsset $asset): static
    {
        return $this->state(fn () => ['organisation_id' => $asset->organisation_id, 'brand_id' => $asset->brand_id, 'media_asset_id' => $asset->id]);
    }
}
