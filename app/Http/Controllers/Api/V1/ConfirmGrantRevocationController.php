<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\Social\ManageGrantRevocations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConfirmGrantRevocationController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, string $requestUuid,
        ManageGrantRevocations $service): JsonResponse
    {
        return response()->json(['data' => $service->confirm($organisation, $request->user(), $requestUuid)]);
    }
}
