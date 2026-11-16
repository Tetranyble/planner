<?php

namespace App\Mcp\Tools\Agent;

use Laravel\Mcp\Server\Attributes\Description;

#[Description('Request mandatory human approval for the exact target and content hash. An agent cannot approve.')]
final class RequestReviewTool extends PlannerAgentActionTool
{
    protected function action(): string
    {
        return 'approval.request';
    }

    protected function payloadHelp(): string
    {
        return 'JSON object: {"work_order_uuid":"...","target_uuid":"..."}';
    }
}
