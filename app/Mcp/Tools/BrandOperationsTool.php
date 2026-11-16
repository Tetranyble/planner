<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Read internal workflow counts for a verified UTC date range. Not social engagement or campaign ROI.')]
#[IsReadOnly]
#[IsIdempotent]
final class BrandOperationsTool extends PlannerReadTool
{
    protected function readKey(): string
    {
        return 'brand_operations';
    }
}
