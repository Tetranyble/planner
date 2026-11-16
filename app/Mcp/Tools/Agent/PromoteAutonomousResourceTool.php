<?php

namespace App\Mcp\Tools\Agent;

use Laravel\Mcp\Server\Attributes\Description;

#[Description('After a research run is READY_FOR_REVIEW, convert its cited findings and generated resource into a normal immutable content draft requiring human review.')]
final class PromoteAutonomousResourceTool extends PlannerAgentActionTool
{
    protected function action(): string
    {
        return 'resource.promote';
    }

    protected function payloadHelp(): string
    {
        return 'JSON object: {"run_uuid":"valid-run-uuid"}';
    }
}
