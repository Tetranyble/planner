<?php

namespace App\Mcp\Tools\Agent;

use App\Models\User;
use App\Services\Agents\OrchestratePlannerAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Read the progress, cited evidence, generated resource and review status of the current delegated agent research runs.')]
final class ListAutonomousResearchTool extends Tool
{
    public function handle(Request $request, OrchestratePlannerAgent $orchestrator): Response
    {
        $actor = request()->user();
        $scope = request()->attributes->get('planner_agent_scope');
        if (! $actor instanceof User || ! is_array($scope)) {
            return Response::error('An active agent delegation is required.');
        }

        return Response::text(json_encode($orchestrator->listing($actor, $scope), JSON_THROW_ON_ERROR));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
