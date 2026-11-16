<?php

namespace App\Mcp\Tools\Agent;

use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create an immutable AI-attributed content draft from one research work order.')]
final class CreateDraftTool extends PlannerAgentActionTool
{
    protected function action(): string
    {
        return 'content.draft';
    }

    protected function payloadHelp(): string
    {
        return 'JSON object: {"work_order_uuid":"...","title":"...","copy_text":"...","content_kind":"TEXT","media_uuids":[]}';
    }
}
