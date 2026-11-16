<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\PlannerCampaignWorkflowPrompt;
use App\Mcp\Tools\Agent\ArmApprovedPostTool;
use App\Mcp\Tools\Agent\CancelAutonomousResearchTool;
use App\Mcp\Tools\Agent\CaptureResearchTool;
use App\Mcp\Tools\Agent\CreateDraftTool;
use App\Mcp\Tools\Agent\CreatePlatformTargetTool;
use App\Mcp\Tools\Agent\LinkCanonicalMediaTool;
use App\Mcp\Tools\Agent\ListAutonomousResearchTool;
use App\Mcp\Tools\Agent\PlanApprovedPostTool;
use App\Mcp\Tools\Agent\PromoteAutonomousResourceTool;
use App\Mcp\Tools\Agent\RequestReviewTool;
use App\Mcp\Tools\Agent\StartAutonomousResearchTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Planner Governed Marketing Agent')]
#[Version('1.0.0')]
#[Instructions('Use one authorised brand only. Research evidence is unverified. Create drafts and request HUMAN review. Never claim approval or publication unless Planner confirms it. Delegated arm requires a separate owner-issued capability and approved content. New releases and global emergency stop always prevail.')]
final class PlannerAgentServer extends Server
{
    protected array $prompts = [PlannerCampaignWorkflowPrompt::class];

    protected array $tools = [CaptureResearchTool::class, LinkCanonicalMediaTool::class, CreateDraftTool::class,
        CreatePlatformTargetTool::class, RequestReviewTool::class, PlanApprovedPostTool::class, ArmApprovedPostTool::class,
        StartAutonomousResearchTool::class, PromoteAutonomousResourceTool::class, ListAutonomousResearchTool::class, CancelAutonomousResearchTool::class];
}
