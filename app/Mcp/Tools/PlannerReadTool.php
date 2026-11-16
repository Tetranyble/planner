<?php

namespace App\Mcp\Tools;

use App\Models\User;
use App\Services\Integrations\PlannerMcpReader;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/** Base implementation deliberately has no dynamic user/organisation/brand arguments. */
abstract class PlannerReadTool extends Tool
{
    abstract protected function readKey(): string;

    public function handle(Request $request, PlannerMcpReader $reader): Response
    {
        $http = request();
        $actor = $http->user();
        $scope = $http->attributes->get('planner_mcp_scope');
        if (! $actor instanceof User || ! is_array($scope)
            || ! isset($scope['organisation'], $scope['brand'])) {
            return Response::error('Read-only grant context missing.');
        }
        $input = [];
        if (in_array($this->readKey(), ['brand_operations', 'publication_calendar'], true)) {
            $input = $request->validate([
                'from' => ['required', 'date_format:Y-m-d'],
                'to' => ['required', 'date_format:Y-m-d'],
            ]);
        }
        // Underlying service rechecks active organisation context, brand membership and Kinship.
        $result = $reader->read($this->readKey(), $actor, $scope['organisation'], $scope['brand'], $input);
        $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        if (strlen($encoded) > 65536) {
            return Response::error('Report exceeds the MCP size limit; narrow the requested range.');
        }

        return Response::text($encoded);
    }

    public function schema(JsonSchema $schema): array
    {
        if (in_array($this->readKey(), ['brand_operations', 'publication_calendar'], true)) {
            return [
                'from' => $schema->string()->description('UTC start date, YYYY-MM-DD')->required(),
                'to' => $schema->string()->description('UTC end date, YYYY-MM-DD (max 90 days)')->required(),
            ];
        }

        return [];
    }
}
