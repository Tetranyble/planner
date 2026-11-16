<?php

namespace App\Mcp\Tools\Agent;

use Laravel\Mcp\Server\Attributes\Description;

#[Description('Record agent-attributed HTTPS research sources, findings and a new work order. This does not verify facts.')]
final class CaptureResearchTool extends PlannerAgentActionTool
{
    protected function action(): string
    {
        return 'research.capture';
    }

    protected function payloadHelp(): string
    {
        return 'JSON object: {"title":"...","objective":"...","findings":"...","sources":[{"url":"https://...","title":"...","note":"..."}]}';
    }
}
