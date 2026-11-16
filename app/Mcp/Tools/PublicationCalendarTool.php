<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Read brand publication plans for a UTC date range; this does not authorize posting.')]
#[IsReadOnly]
#[IsIdempotent]
final class PublicationCalendarTool extends PlannerReadTool
{
    protected function readKey(): string
    {
        return 'publication_calendar';
    }
}
