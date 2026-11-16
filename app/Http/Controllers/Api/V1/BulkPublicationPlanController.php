<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageBulkPublicationPlans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class BulkPublicationPlanController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, ManageBulkPublicationPlans $service, ApiResponse $response): JsonResponse
    {
        $input = Validator::make($request->all(), [
            'plans' => ['required', 'array', 'min:1', 'max:20'],
            'plans.*.item_uuid' => ['required', 'uuid'],
            'plans.*.target_uuid' => ['required', 'uuid'],
            'plans.*.approval_request_id' => ['required', 'integer', 'min:1'],
            'plans.*.occurrence_key' => ['required', 'uuid'],
            'plans.*.run_at' => ['required', 'string', 'max:48'],
        ])->validate();

        return $response->created($service->create($request->user(), $organisation, $brand, $input['plans']));
    }
}
