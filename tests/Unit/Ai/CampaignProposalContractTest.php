<?php

namespace Tests\Unit\Ai;

use PHPUnit\Framework\TestCase;

final class CampaignProposalContractTest extends TestCase
{
    public function test_campaign_proposals_have_no_posting_side_effects(): void
    {
        $source = file_get_contents(__DIR__.'/../../../app/Services/Ai/ManageCampaignProposals.php');
        $this->assertStringContainsString("'approval_request.update'", $source);
        $this->assertStringContainsString('hash_equals', $source);
        $this->assertStringContainsString('requested_by_user_id', $source);
        $this->assertStringNotContainsString('publication_jobs', $source);
        $this->assertStringNotContainsString('dispatch(', $source);
    }
}
