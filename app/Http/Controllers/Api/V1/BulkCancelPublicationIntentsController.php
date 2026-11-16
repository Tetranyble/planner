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

final class BulkCancelPublicationIntentsController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, ManagePublicationIntents $service, ApiResponse $response): JsonResponse
    {
        $input = Validator::make($request->all(), ['ids' => ['required', 'array', 'min:1', 'max:50'], 'ids.*' => ['required', 'integer', 'min:1']])->validate();

        return $response->success($service->cancelMany($request->user(), $organisation, $brand, $input['ids']));
    }
}
