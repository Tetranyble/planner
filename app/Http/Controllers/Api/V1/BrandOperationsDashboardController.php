<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Analytics\BrandOperationsDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class BrandOperationsDashboardController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand,
        BrandOperationsDashboard $dashboard, ApiResponse $response): JsonResponse
    {
        $input = Validator::make($request->query(), [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ])->validate();

        return $response->success($dashboard->get($request->user(), $organisation, $brand,
            $input['from'], $input['to']));
    }
}
