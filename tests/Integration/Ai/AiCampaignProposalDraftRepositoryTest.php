<?php

namespace Tests\Integration\Ai;

use App\Models\AiCampaignProposal;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AiCampaignProposalRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Execute on disposable MySQL 8. Service authorization requires a real Kinship context. */
final class AiCampaignProposalDraftRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_campaign_draft_links_are_tenant_scoped_and_idempotent_at_database_boundary(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $other = Brand::factory()->forOrganisation($org)->create();
        $author = User::factory()->create();
        $editor = User::factory()->create();
        $proposal = AiCampaignProposal::factory()->create([
            'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'requested_by_user_id' => $author->id, 'status' => 'APPROVED',
            'reviewed_by_user_id' => $editor->id, 'reviewed_at' => now(),
        ]);
        $item = ContentItem::factory()->forBrand($brand)->create(['created_by_user_id' => $editor->id]);
        $repo = app(AiCampaignProposalRepositoryContract::class);
        $this->assertNull($repo->existingDraft($org, $brand, $proposal));
        $repo->linkDraft($org, $brand, $proposal, $item, $editor);
        $link = $repo->existingDraft($org, $brand, $proposal);
        $this->assertNotNull($link);
        $this->assertSame($item->id, $link->content_item_id);
        $this->assertSame($proposal->result_hash, $link->approved_proposal_hash);
        $this->assertNull($repo->existingDraft($org, $other, $proposal));
        $this->assertDatabaseCount('ai_campaign_proposal_drafts', 1);
        $this->assertDatabaseCount('publication_jobs', 0);
        $this->assertDatabaseCount('published_posts',0);
    }
}
