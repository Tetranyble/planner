<?php

namespace Database\Factories;

use App\Domain\Content\ContentVersionPayload;
use App\Models\ContentItem;
use App\Models\ContentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentVersion> */
final class ContentVersionFactory extends Factory
{
    protected $model = ContentVersion::class;

    /** Scoped foreign keys are intentionally required via forBrand() or a more specific factory state. */
    public function definition(): array
    {
        return ['version_number' => 1, 'copy_text' => 'A verified demonstration draft',
            'body_json' => ['schema_version' => 1, 'format' => 'plain_text'],
            'ai_generated' => null, 'includes_sensitive_claim' => null,
            'content_hash' => ContentVersionPayload::hash('A verified demonstration draft', [])];
    }

    public function forItem(ContentItem $item, int $number = 1): static
    {
        return $this->state(fn () => [
            'organisation_id' => $item->organisation_id, 'brand_id' => $item->brand_id,
            'content_item_id' => $item->id, 'version_number' => $number,
        ]);
    }
}
