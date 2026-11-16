<?php

namespace App\Mcp\Tools\Agent;

use Laravel\Mcp\Server\Attributes\Description;

#[Description('Link an existing verified Tetranyble Storage media asset for later drafting. Never uploads raw media.')]
final class LinkCanonicalMediaTool extends PlannerAgentActionTool
{
    protected function action(): string
    {
        return 'media.link';
    }

    protected function payloadHelp(): string
    {
        return 'JSON object: {"storage_media_id":123}';
    }
}
