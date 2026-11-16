<?php

namespace Tests\Feature\Content;

use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\ContentVersion;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\PublicationJob;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Disposable MySQL + installed Kinship package required. Never sends provider traffic. */
final class BulkContentVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_variants_are_atomic_idempotent_and_never_publish(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => 'Bulk Variant Studio'])->assertCreated();
        $org = Organisation::query()->where('name', 'Bulk Variant Studio')->firstOrFail();
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/brands", ['name' => 'Bulk Brand'])->assertCreated();
        $brand = Brand::query()->where('organisation_id', $org->id)->firstOrFail();
        $item = ContentItem::factory()->forBrand($brand)->create();
        $version = ContentVersion::factory()->forItem($item)->create();
        $accounts = [];
        foreach (['x', 'linkedin'] as $provider) {
            $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider($provider)->create();
            $accounts[$provider] = SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create(['status' => 'ACTIVE']);
        }
        $url = "/api/v1/organisations/{$org->uuid}/brands/{$brand->uuid}/content/{$item->uuid}/targets/bulk";
        $payload = ['version_uuid' => $version->uuid, 'variants' => [
            ['social_account_uuid' => $accounts['x']->uuid, 'platform_caption' => 'Safe short X text'],
            ['social_account_uuid' => $accounts['linkedin']->uuid, 'platform_caption' => str_repeat('L', 281)],
        ]];
        $this->actingAs($owner)->postJson($url, $payload)->assertCreated()
            ->assertJsonPath('data.created_or_reused', 2)->assertJsonPath('data.can_dispatch', false);
        $this->actingAs($owner)->postJson($url, $payload)->assertCreated();
        $this->assertSame(2, ContentTarget::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)->count());
        $this->assertSame(0, PublicationJob::query()->where('brand_id', $brand->id)->count());

        $invalid = $payload;
        $invalid['variants'][0]['platform_caption'] = str_repeat('x', 281);
        $invalid['variants'][1]['platform_caption'] = 'Another valid variation';
        $this->actingAs($owner)->postJson($url, $invalid)->assertUnprocessable();
        $this->assertSame(2, ContentTarget::query()->where('organisation_id', $org->id)->where('brand_id',$brand->id)->count());
    }
}
