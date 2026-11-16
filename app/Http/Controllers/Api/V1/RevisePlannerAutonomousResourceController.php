<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Agents\ManagePlannerAgentDelegations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RevisePlannerAutonomousResourceController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, string $uuid,
        ManagePlannerAgentDelegations $manager, ApiResponse $response): JsonResponse
    {
        $input = $request->validate([
            'delegation_id' => ['required', 'integer', 'min:1'],
            'expected_resource_hash' => ['required', 'regex:/\A[a-f0-9]{64}\z/D'],
            'resource_text' => ['required', 'string', 'min:20', 'max:6000'],
            'review_note' => ['required', 'string', 'min:5', 'max:250'],
        ]);
        $updated = $manager->reviseResource($request->user(), $organisation, $brand,
            (int) $input['delegation_id'], $uuid, $input['expected_resource_hash'],
            $input['resource_text'], $input['review_note']);

        return $response->success($updated, headers: ['Cache-Control' => 'private, no-store']);
    }
}
