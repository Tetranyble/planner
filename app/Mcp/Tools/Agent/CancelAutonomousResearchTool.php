<?php

namespace App\Mcp\Tools\Agent;

use Laravel\Mcp\Server\Attributes\Description;

#[Description('Cancel an unstarted or completed autonomous run. In-flight external research cannot be interrupted and will be quarantined when uncertain.')]
final class CancelAutonomousResearchTool extends PlannerAgentActionTool
{
    protected function action(): string
    {
        return 'research.cancel';
    }

    protected function payloadHelp(): string
    {
        return 'JSON object: {"run_uuid":"valid-run-uuid"}';
    }
}
