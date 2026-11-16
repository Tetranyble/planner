<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Publishing\ExecutePublicationPlans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class ArmPublicationPlanController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, int $jobId,
        ExecutePublicationPlans $service, ApiResponse $response): JsonResponse
    {
        $input = Validator::make($request->all(), ['expected_version' => ['required', 'integer', 'min:0'],
            'acknowledge_live_delivery' => ['required', 'accepted']])->validate();

        return $response->success($service->arm($request->user(), $organisation, $brand, $jobId, (int) $input['expected_version']));
    }
}
