<?php

namespace Tests\Feature\Content;

use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\ContentVersion;
use App\Models\MediaAsset;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Run with the Kinship migration catalogue and real disposable MySQL. */
final class ContentStudioEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function provision(User $owner, string $label): array
    {
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => $label])->assertCreated();
        $org = Organisation::query()->where('name', $label)->firstOrFail();
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/brands", ['name' => $label.' Brand'])->assertCreated();
        $brand = Brand::query()->where('organisation_id', $org->id)->firstOrFail();

        return [$org, $brand, "/api/v1/organisations/{$org->uuid}/brands/{$brand->uuid}/content"];
    }

    public function test_draft_create_and_immutable_version_append_reject_stale_writer(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$url] = $this->provision($owner, 'Content Studio Main');
        $this->actingAs($owner)->postJson($url, ['title' => 'School operations tips', 'content_kind' => 'TEXT', 'copy_text' => 'Draft one'])->assertCreated()
            ->assertJsonPath('data.version_number', 1);
        $item = ContentItem::query()->where('brand_id', $brand->id)->firstOrFail();
        $revisionUrl = "{$url}/{$item->uuid}/versions";
        $this->actingAs($owner)->postJson($revisionUrl, ['expected_version' => 1, 'copy_text' => 'Draft two'])->assertCreated()
            ->assertJsonPath('data.version_number', 2);
        $this->actingAs($owner)->postJson($revisionUrl, ['expected_version' => 1, 'copy_text' => 'Stale'])->assertStatus(409);
        $this->assertSame(2, ContentVersion::query()->where('content_item_id', $item->id)->count());
        $this->assertSame('Draft one', ContentVersion::query()->where('content_item_id', $item->id)->where('version_number', 1)->firstOrFail()->copy_text);
        $this->actingAs($owner)->getJson("{$url}/{$item->uuid}")->assertOk()->assertJsonPath('data.versions.0.version_number', 2);
    }

    public function test_cross_brand_and_cross_organisation_reads_and_versions_are_denied(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        [$orgA,$brandA,$urlA] = $this->provision($first, 'Content Owner A');
        [$orgB,$brandB,$urlB] = $this->provision($second, 'Content Owner B');
        $item = ContentItem::factory()->forBrand($brandB)->create();
        ContentVersion::factory()->forItem($item)->create();
        $this->actingAs($first)->getJson("{$urlB}/{$item->uuid}")->assertForbidden();
        $this->actingAs($first)->postJson("{$urlB}/{$item->uuid}/versions", ['expected_version' => 1, 'copy_text' => 'Malicious update'])->assertForbidden();
        $this->actingAs($first)->getJson("{$urlA}/{$item->uuid}")->assertNotFound();
        $this->assertSame(1, ContentVersion::query()->where('content_item_id', $item->id)->count());
    }

    public function test_unrelated_brand_media_is_rejected_and_does_not_create_version(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$url] = $this->provision($owner, 'Content Media A');
        // Same-organisation second brand must not implicitly inherit asset access.
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/brands", ['name' => 'Other brand'])->assertCreated();
        $other = Brand::query()->where('organisation_id', $org->id)->where('id', '!=', $brand->id)->firstOrFail();
        $asset = MediaAsset::factory()->forBrand($other)->create();
        $this->actingAs($owner)->postJson($url, ['title' => 'Unauthorized asset test', 'content_kind' => 'TEXT',
            'copy_text' => 'Should fail', 'media_uuids' => [$asset->uuid]])->assertNotFound();
        $this->assertDatabaseMissing('content_items', ['organisation_id' => $org->id, 'brand_id' => $brand->id, 'title' => 'Unauthorized asset test']);
    }

    public function test_invalid_payloads_and_guest_access_are_rejected(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$url] = $this->provision($owner, 'Content Validation A');
        $this->actingAs($owner)->postJson($url, ['title' => 'x', 'content_kind' => 'INVALID', 'copy_text' => ''])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content_kind', 'copy_text']);
        $this->assertSame(0, ContentItem::query()->where('brand_id', $brand->id)->count());
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->getJson($url)->assertUnauthorized();
    }

    public function test_platform_target_is_an_idempotent_draft_not_a_publishing_job(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$url] = $this->provision($owner, 'Content Targets A');
        $item = ContentItem::factory()->forBrand($brand)->create();
        $version = ContentVersion::factory()->forItem($item)->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)
            ->create(['status' => 'ACTIVE']);
        $payload = ['version_uuid' => $version->uuid, 'social_account_uuid' => $account->uuid,
            'platform_caption' => 'Draft only — do not publish'];
        $this->actingAs($owner)->postJson("{$url}/{$item->uuid}/targets", $payload)
            ->assertCreated()->assertJsonPath('data.publishable', false)->assertJsonPath('data.mode', 'ASSISTED');
        $this->actingAs($owner)->postJson("{$url}/{$item->uuid}/targets", $payload)->assertCreated();
        $this->assertSame(1, ContentTarget::query()->where('brand_id', $brand->id)->count());
        $this->assertDatabaseHas('content_targets', ['brand_id' => $brand->id, 'publishing_mode' => 'ASSISTED']);
    }

    public function test_unowned_storage_record_cannot_be_linked_as_a_brand_asset(): void
    {
        $owner = User::factory()->create();
        [$org,$brand,$url] = $this->provision($owner, 'Content Storage Guard A');
        $this->actingAs($owner)->postJson("{$url}/media-assets", ['storage_media_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('storage_media_id');
        $this->assertSame(0, MediaAsset::query()->where('brand_id', $brand->id)->count());
    }
}
