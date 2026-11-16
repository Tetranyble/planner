<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Analytics\BrandOperationsDashboard;
use App\Services\Analytics\ProviderPerformanceDashboard;
use App\Services\Publishing\PublicationOperations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlannerIntegrationOperationsController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand, BrandOperationsDashboard $dashboard, ApiResponse $response): JsonResponse
    {
        $input = $request->validate(['from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d']]);

        return $response->success($dashboard->get($request->user(), $organisation, $brand,
            $input['from'], $input['to']));
    }

    public function show(Request $request, Organisation $organisation, Brand $brand,
        string $resource, ProviderPerformanceDashboard $performance, PublicationOperations $publication, ApiResponse $response): JsonResponse
    {
        return match ($resource) {
            'provider-performance' => $response->success($performance->show($request->user(), $organisation, $brand)),
            'publication-operations' => $response->success($publication->overview($request->user(), $organisation, $brand)),
            default => abort(404),
        };
    }
}
