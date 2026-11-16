<?php

namespace Tests\Integration\Content;

use App\Domain\Content\ContentVersionPayload;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\ContentVersion;
use App\Models\Organisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MySQL integration coverage: UNKNOWN is distinct from NO. */
final class ContentRiskDeclarationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_nullable_risk_declarations_survive_database_roundtrip_and_immutable_history(): void
    {
        $organisation = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($organisation)->create();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $legacy = ContentVersion::factory()->forItem($item, 1)->create();
        $declared = ContentVersion::factory()->forItem($item, 2)->create([
            'ai_generated' => false,
            'includes_sensitive_claim' => true,
            'content_hash' => ContentVersionPayload::hash('A verified demonstration draft', [], false, true),
        ]);
        $this->assertNull($legacy->fresh()->ai_generated);
        $this->assertNull($legacy->fresh()->includes_sensitive_claim);
        $this->assertFalse($declared->fresh()->ai_generated);
        $this->assertTrue($declared->fresh()->includes_sensitive_claim);
        $this->expectException(\LogicException::class);
        $declared->update(['ai_generated' => true]);
    }
}
