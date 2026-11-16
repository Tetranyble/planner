<?php

namespace App\Mcp\Tools\Agent;

use Laravel\Mcp\Server\Attributes\Description;

#[Description('Prepare a held, future-dated publication plan only after the target is human-approved.')]
final class PlanApprovedPostTool extends PlannerAgentActionTool
{
    protected function action(): string
    {
        return 'publication.plan';
    }

    protected function payloadHelp(): string
    {
        return 'JSON object: {"work_order_uuid":"...","target_uuid":"...","approval_id":123,"occurrence_key":"unique-event-id","scheduled_at":"2026-12-01T15:00:00+01:00"}';
    }
}
