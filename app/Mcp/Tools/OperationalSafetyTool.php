<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Read counts of uncertain, overdue and failed operations without changing their state.')]
#[IsReadOnly]
#[IsIdempotent]
final class OperationalSafetyTool extends PlannerReadTool
{
    protected function readKey(): string
    {
        return 'operational_safety';
    }
}
