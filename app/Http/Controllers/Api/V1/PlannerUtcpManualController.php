<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Integrations\PlannerUtcpManual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlannerUtcpManualController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand,
        PlannerUtcpManual $manual): JsonResponse
    {
        return response()->json($manual->build($request->user(), $organisation, $brand), 200,
            ['Cache-Control' => 'no-store, private']);
    }
}
