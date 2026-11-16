<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\StoreGrantReconciliationEvidenceRequest;
use App\Models\Organisation;
use App\Services\Social\ReconcileGrantRevocations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Resource methods only; outcome decisions live in an invokable controller. */
final class GrantReconciliationController extends Controller
{
    public function index(Request $request, Organisation $organisation, ReconcileGrantRevocations $service): JsonResponse
    {
        return response()->json(['data' => $service->directory($organisation, $request->user())]);
    }

    public function store(StoreGrantReconciliationEvidenceRequest $request, Organisation $organisation,
        string $requestUuid, ReconcileGrantRevocations $service): JsonResponse
    {
        return response()->json(['data' => $service->submit($organisation, $request->user(), $requestUuid, $request->validated())], 201);
    }
}
