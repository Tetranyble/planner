<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Publishing\PrepareMediaPublications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AbortMediaPublicationPreparationController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, int $preparationId,
        PrepareMediaPublications $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);

        return $response->success($service->abort($request->user(), $organisation, $brand, $preparationId,
            (int) $data['expected_version']));
    }
}
