<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManagePublicationIntents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class ReschedulePublicationIntentController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, int $jobId, ManagePublicationIntents $service, ApiResponse $response): JsonResponse
    {
        $input = Validator::make($request->all(), [
            'run_at' => ['required', 'string', 'max:48'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ])->validate();

        return $response->success($service->reschedule($request->user(), $organisation, $brand, $jobId, $input['run_at'], (int) $input['expected_version']));
    }
}
