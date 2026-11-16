<?php

use App\Mcp\Servers\PlannerAgentServer;
use App\Mcp\Servers\PlannerReadOnlyServer;
use Laravel\Mcp\Facades\Mcp;

// Uses a pre-issued, revocable, single-brand Sanctum token. No public OAuth endpoints.
// POST is permitted ONLY as the MCP transport for the five read-only tools.
Mcp::web('/mcp/planner', PlannerReadOnlyServer::class)
    ->middleware(['auth:sanctum', 'planner.mcp', 'throttle:30,1']);

// Independently issued, revocable, single-brand agent token; NOT the read-only MCP grant.
// This endpoint cannot approve and never bypasses existing publication release gates.
Mcp::web('/mcp/planner-agent', PlannerAgentServer::class)
    ->middleware(['auth:sanctum', 'planner.agent', 'throttle:20,1']);

// Explicit, application-owned OAuth2 routes. Passport::ignoreRoutes() prevents hidden routes.
// Built-in MCP discovery registration is deliberately not used; app-owned routes follow.
require __DIR__.'/planner_oauth.php';

// Passport access tokens are accepted ONLY at this isolated OAuth MCP resource.
Mcp::web('/mcp/planner-agent-oauth', PlannerAgentServer::class)
    ->middleware(['auth:api', 'planner.oauth.agent', 'throttle:20,1']);
