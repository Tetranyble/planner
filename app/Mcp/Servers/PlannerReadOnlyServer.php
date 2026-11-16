<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\BrandOperationsTool;
use App\Mcp\Tools\OperationalSafetyTool;
use App\Mcp\Tools\ProviderPerformanceTool;
use App\Mcp\Tools\PublicationCalendarTool;
use App\Mcp\Tools\PublicationHistoryTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Planner Brand Read-Only')]
#[Version('1.0.0')]
#[Instructions('Read-only tenant-isolated Planner reports. These tools never approve, schedule, retry or publish. No combined platform ROI claims.')]
final class PlannerReadOnlyServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        BrandOperationsTool::class,
        PublicationCalendarTool::class,
        OperationalSafetyTool::class,
        ProviderPerformanceTool::class,
        PublicationHistoryTool::class,
    ];
}
