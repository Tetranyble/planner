<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Read provider-specific verified observations; do not combine incompatible metric definitions.')]
#[IsReadOnly]
#[IsIdempotent]
final class ProviderPerformanceTool extends PlannerReadTool
{
    protected function readKey(): string
    {
        return 'provider_performance';
    }
}
