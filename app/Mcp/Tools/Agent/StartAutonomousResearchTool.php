<?php

namespace App\Mcp\Tools\Agent;

use Laravel\Mcp\Server\Attributes\Description;

#[Description('Start a durable, source-attributed web research and editorial resource run. No publication or self-approval. Agent must poll run status before using the draft.')]
final class StartAutonomousResearchTool extends PlannerAgentActionTool
{
    protected function action(): string
    {
        return 'research.autorun';
    }

    protected function payloadHelp(): string
    {
        return 'JSON object: {"run_key":"unique-key-at-least-12-chars","title":"topic","objective":"research goal","resource_kind":"SOCIAL_COPY|CAMPAIGN_BRIEF"}';
    }
}
