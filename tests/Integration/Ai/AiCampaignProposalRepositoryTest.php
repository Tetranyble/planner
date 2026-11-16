<?php

namespace Tests\Integration\Ai;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AiCampaignProposalRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AiCampaignProposalRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposals_are_tenant_scoped_and_reviewer_disposition_persists(): void
    {
        $first = Organisation::factory()->create();
        $second = Organisation::factory()->create();
        $brandOne = Brand::factory()->create(['organisation_id' => $first->id]);
        $brandTwo = Brand::factory()->create(['organisation_id' => $second->id]);
        $author = User::factory()->create();
        $reviewer = User::factory()->create();
        $repo = app(AiCampaignProposalRepositoryContract::class);
        $proposal = $repo->create($first, $brandOne, $author, [
            'title' => 'Product introduction', 'objective' => 'Introduce the service',
            'audience' => 'School administrators', 'platforms' => ['linkedin', 'facebook'],
            'duration_days' => 7, 'proposal_text' => 'An unapproved editorial outline',
            'model' => 'fixture', 'prompt_hash' => hash('sha256', 'input'),
            'result_hash' => hash('sha256', 'An unapproved editorial outline'),
        ]);
        $this->assertSame('PROPOSED', $proposal->status);
        $this->assertNull($repo->list($second, $brandTwo)->first());
        $this->assertSame($proposal->uuid, $repo->find($first, $brandOne, $proposal->uuid)->uuid);
        $repo->review($proposal, $reviewer, 'REJECTED', 'Contains unsupported claims.');
        $this->assertSame('REJECTED', $repo->find($first, $brandOne, $proposal->uuid)->status);
        $this->assertSame($reviewer->id, $repo->find($first, $brandOne, $proposal->uuid)->reviewed_by_user_id);
        $this->assertDatabaseCount('publication_jobs',0);
    }
}
