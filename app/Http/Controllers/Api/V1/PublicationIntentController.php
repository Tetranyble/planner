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

final class PublicationIntentController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand, ManagePublicationIntents $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->listing($request->user(), $organisation, $brand));
    }

    public function store(Request $request, Organisation $organisation, Brand $brand, string $itemUuid, string $targetUuid, int $requestId, ManagePublicationIntents $service, ApiResponse $response): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'occurrence_key' => ['required', 'uuid'],
            'run_at' => ['required', 'string', 'max:48'],
        ])->validate();

        return $response->created($service->create($request->user(), $organisation, $brand, $itemUuid, $targetUuid, $requestId, $data['occurrence_key'], $data['run_at']));
    }
}
