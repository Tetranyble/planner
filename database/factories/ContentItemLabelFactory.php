<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\ContentItemLabel;
use App\Models\ContentLabel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentItemLabel> */
final class ContentItemLabelFactory extends Factory
{
    protected $model = ContentItemLabel::class;

    /** Scoped foreign keys are intentionally required via forBrand() or a more specific factory state. */
    public function definition(): array
    {
        return []; // All columns are scoped foreign keys; set them with forBrand()/forOrganisation() or state().
    }

    public function forItemAndLabel(ContentItem $item, ContentLabel $label): static
    {
        if ((int) $item->organisation_id !== (int) $label->organisation_id || (int) $item->brand_id !== (int) $label->brand_id) {
            throw new \InvalidArgumentException('Item and label must be in the same brand.');
        }

        return $this->state(fn () => ['organisation_id' => $item->organisation_id, 'brand_id' => $item->brand_id,
            'content_item_id' => $item->id, 'content_label_id' => $label->id]);
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
