<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Publishing\PrepareMediaPublications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MediaPublicationPreparationController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand,
        PrepareMediaPublications $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->list($request->user(), $organisation, $brand));
    }

    public function show(Request $request, Organisation $organisation, Brand $brand, int $preparationId,
        PrepareMediaPublications $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->show($request->user(), $organisation, $brand, $preparationId));
    }

    public function store(Request $request, Organisation $organisation, Brand $brand,
        PrepareMediaPublications $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validate(['publication_job_id' => ['required', 'integer', 'min:1']]);

        return $response->success($service->prepare($request->user(), $organisation, $brand, (int) $data['publication_job_id']));
    }
}
