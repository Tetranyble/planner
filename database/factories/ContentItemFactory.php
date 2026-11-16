<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ContentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentItem> */
final class ContentItemFactory extends Factory
{
    protected $model = ContentItem::class;

    /** Scoped foreign keys are intentionally required via forBrand() or a more specific factory state. */
    public function definition(): array
    {
        return ['title' => fake()->sentence(5), 'content_kind' => 'TEXT', 'state' => 'DRAFT'];
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
