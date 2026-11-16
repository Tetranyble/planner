<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Read tenant-scoped publication attempts and history; never retry, approve or publish.')]
#[IsReadOnly]
#[IsIdempotent]
final class PublicationHistoryTool extends PlannerReadTool
{
    protected function readKey(): string
    {
        return 'publication_history';
    }
}
