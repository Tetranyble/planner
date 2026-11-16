<?php

namespace Tests\Integration\Content;

use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\ContentVersion;
use App\Models\Organisation;
use App\Repository\Contracts\ContentStudioRepositoryContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Real-MySQL test: foreign keys and tenant clauses must agree. */
final class ContentStudioRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_backed_content_queries_cannot_escape_brand_scope(): void
    {
        $org = Organisation::factory()->create();
        $a = Brand::factory()->forOrganisation($org)->create();
        $b = Brand::factory()->forOrganisation($org)->create();
        $item = ContentItem::factory()->forBrand($b)->create();
        ContentVersion::factory()->forItem($item)->create();
        $repository = app(ContentStudioRepositoryContract::class);
        $this->assertSame(0, $repository->paginate($org, $a, 20)->total());
        $this->assertSame(1, $repository->paginate($org, $b, 20)->total());
        $this->expectException(ModelNotFoundException::class);
        $repository->findItem($org, $a, $item->uuid);
    }
}
