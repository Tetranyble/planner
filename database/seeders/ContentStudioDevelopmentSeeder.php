<?php

namespace Database\Seeders;

use App\Domain\Content\ContentVersionPayload;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\ContentVersion;
use App\Models\Organisation;
use Illuminate\Database\Seeder;
use LogicException;

/** Run explicitly in local/testing only; no fabricated real brands or social authorizations. */
final class ContentStudioDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo content seeding is local/testing only.');
        }
        $organisation = Organisation::query()->orderBy('id')->first();
        if (! $organisation) {
            $this->command?->warn('No organisation exists; seed an organisation before content.');

            return;
        }
        $brand = Brand::query()->where('organisation_id', $organisation->id)->orderBy('id')->first();
        if (! $brand) {
            $this->command?->warn('No brand exists; seed a brand before content.');

            return;
        }
        $title = 'Planner demonstration — draft campaign idea';
        $item = ContentItem::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('title', $title)->first();
        if (! $item) {
            $item = ContentItem::factory()->forBrand($brand)->create(['title' => $title, 'content_kind' => 'TEXT', 'state' => 'DRAFT']);
        }
        $text = 'Draft only. Verify all claims and evidence before requesting publication.';
        $existing = ContentVersion::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('content_item_id', $item->id)->where('version_number', 1)->exists();
        if (! $existing) {
            ContentVersion::factory()->forItem($item)->create([
                'copy_text' => $text, 'body_json' => ContentVersionPayload::body(),
                'content_hash' => ContentVersionPayload::hash($text, []),
            ]);
        }
    }
}
