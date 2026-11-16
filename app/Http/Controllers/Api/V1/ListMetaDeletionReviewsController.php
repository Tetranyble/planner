<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\Social\ReviewMetaDataDeletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListMetaDeletionReviewsController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, ReviewMetaDataDeletion $service): JsonResponse
    {
        return response()->json(['data' => $service->directory($organisation, $request->user())]);
    }
}
