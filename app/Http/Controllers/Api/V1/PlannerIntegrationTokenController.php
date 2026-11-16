<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Integrations\ManagePlannerIntegrationGrants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlannerIntegrationTokenController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand,
        ManagePlannerIntegrationGrants $manager, ApiResponse $response): JsonResponse
    {
        return $response->success($manager->list($request->user(), $organisation, $brand), headers: ['Cache-Control' => 'no-store, private']);
    }

    public function store(Request $request, Organisation $organisation, Brand $brand,
        ManagePlannerIntegrationGrants $manager, ApiResponse $response): JsonResponse
    {
        $input = $request->validate(['name' => ['required', 'string', 'min:3', 'max:100'],
            'expires_in_days' => ['required', 'integer', 'min:1', 'max:30']]);

        return $response->success($manager->issue($request->user(), $organisation, $brand,
            $input['name'], (int) $input['expires_in_days']), statusCode: 201, headers: ['Cache-Control' => 'no-store, private']);
    }

    public function destroy(Request $request, Organisation $organisation, Brand $brand, int $grantId,
        ManagePlannerIntegrationGrants $manager, ApiResponse $response): JsonResponse
    {
        return $response->success($manager->revoke($request->user(), $organisation, $brand, $grantId));
    }
}
