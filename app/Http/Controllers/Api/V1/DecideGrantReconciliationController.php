<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\DecideGrantReconciliationRequest;
use App\Models\Organisation;
use App\Services\Social\ReconcileGrantRevocations;
use Illuminate\Http\JsonResponse;

final class DecideGrantReconciliationController extends Controller
{
    public function __invoke(DecideGrantReconciliationRequest $request, Organisation $organisation,
        string $requestUuid, ReconcileGrantRevocations $service): JsonResponse
    {
        return response()->json(['data' => $service->decide($organisation, $request->user(), $requestUuid, $request->validated())]);
    }
}
