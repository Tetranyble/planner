<?php

namespace Database\Factories;

use App\Models\ContentTarget;
use App\Models\ContentTargetMedia;
use App\Models\MediaVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentTargetMedia> */
final class ContentTargetMediaFactory extends Factory
{
    protected $model = ContentTargetMedia::class;

    /** Scoped foreign keys are intentionally required via forBrand() or a more specific factory state. */
    public function definition(): array
    {
        return ['sort_order' => 0, 'role' => 'PRIMARY'];
    }

    public function forTargetAndVariant(ContentTarget $target, MediaVariant $variant): static
    {
        if ((int) $target->organisation_id !== (int) $variant->organisation_id || (int) $target->brand_id !== (int) $variant->brand_id) {
            throw new \InvalidArgumentException('Target and variant must be in the same brand.');
        }

        return $this->state(fn () => ['organisation_id' => $target->organisation_id, 'brand_id' => $target->brand_id,
            'content_target_id' => $target->id, 'media_variant_id' => $variant->id]);
    }
}
