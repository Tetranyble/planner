<?php

namespace App\Mcp\Tools\Agent;

use Laravel\Mcp\Server\Attributes\Description;

#[Description('Prepare a social-account-specific target attached to a work-order draft.')]
final class CreatePlatformTargetTool extends PlannerAgentActionTool
{
    protected function action(): string
    {
        return 'content.target';
    }

    protected function payloadHelp(): string
    {
        return 'JSON object: {"work_order_uuid":"...","version_uuid":"...","social_account_uuid":"...","platform_caption":"...","publishing_mode":"ASSISTED"}';
    }
}
