<?php

namespace App\Mcp\Tools\Agent;

use Laravel\Mcp\Server\Attributes\Description;

#[Description('With explicit owner delegation only, arm an already-approved held plan. Emergency stop and provider release gates remain enforced.')]
final class ArmApprovedPostTool extends PlannerAgentActionTool
{
    protected function action(): string
    {
        return 'publication.arm';
    }

    protected function payloadHelp(): string
    {
        return 'JSON object: {"work_order_uuid":"...","job_id":123,"expected_version":0}';
    }
}
