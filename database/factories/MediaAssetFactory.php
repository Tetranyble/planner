<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaAsset> */
final class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    /** Scoped foreign keys are intentionally required via forBrand() or a more specific factory state. */
    public function definition(): array
    {
        return ['file_name' => 'fixture-image.png', 'mime_type' => 'image/png', 'byte_size' => 2048,
            'asset_state' => 'READY', 'source_type' => 'UPLOAD', 'sha256' => str_repeat('a', 64)];
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }

    public function forStorageMedia(int $storageMediaId): static
    {
        return $this->state(fn () => ['storage_media_id' => $storageMediaId]);
    }
}
