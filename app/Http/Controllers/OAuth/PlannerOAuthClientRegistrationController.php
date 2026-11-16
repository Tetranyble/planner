<?php

namespace App\Http\Controllers\OAuth;

use App\Services\OAuth\RegisterPlannerOAuthClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlannerOAuthClientRegistrationController
{
    public function __invoke(Request $request, RegisterPlannerOAuthClient $registration): JsonResponse
    {
        return response()->json($registration->register($request->all()), 201);
    }
}
