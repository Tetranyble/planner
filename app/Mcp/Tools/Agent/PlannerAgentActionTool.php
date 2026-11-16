<?php

namespace App\Mcp\Tools\Agent;

use App\Models\User;
use App\Services\Agents\OrchestratePlannerAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/** Agent transport accepts a bounded JSON payload and routes through the same service as REST. */
abstract class PlannerAgentActionTool extends Tool
{
    abstract protected function action(): string;

    abstract protected function payloadHelp(): string;

    public function handle(Request $request, OrchestratePlannerAgent $service): Response
    {
        $input = $request->validate(['idempotency_key' => ['required', 'string', 'min:12', 'max:96'],
            'payload_json' => ['required', 'string', 'max:65000']]);
        try {
            $payload = json_decode($input['payload_json'], true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return Response::error('payload_json must be a JSON object.');
        }
        if (! is_array($payload) || array_is_list($payload)) {
            return Response::error('payload_json must be a JSON object.');
        }
        $http = request();
        $actor = $http->user();
        $scope = $http->attributes->get('planner_agent_scope');
        if (! $actor instanceof User || ! is_array($scope)) {
            return Response::error('An authorized agent delegation is required.');
        }
        $result = $service->execute($actor, $scope, $this->action(), $input['idempotency_key'], $payload);

        return Response::text(json_encode($result, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['idempotency_key' => $schema->string()->description('Unique stable key for this logical action (12-96 characters). Reuse only on exact retries.')->required(),
            'payload_json' => $schema->string()->description($this->payloadHelp())->required()];
    }
}
