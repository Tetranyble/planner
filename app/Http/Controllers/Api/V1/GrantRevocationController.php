<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\PrepareGrantRevocationRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Social\ManageGrantRevocations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GrantRevocationController extends Controller
{
    public function index(Request $request, Organisation $organisation, ManageGrantRevocations $service): JsonResponse
    {
        return response()->json(['data' => $service->directory($organisation, $request->user())]);
    }

    public function store(PrepareGrantRevocationRequest $request, Organisation $organisation, Brand $brand,
        string $accountUuid, ManageGrantRevocations $service): JsonResponse
    {
        $result = $service->prepare($organisation, $brand, $request->user(), $accountUuid, $request->validated('reason'));

        return response()->json(['data' => $result], 201);
    }
}
