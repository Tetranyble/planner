<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\Social\ManagePrivacyRetentionFollowups;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AcknowledgePrivacyRetentionReviewController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, int $followupId,
        ManagePrivacyRetentionFollowups $service): JsonResponse
    {
        $input = $request->validate(['notes' => ['required', 'string', 'min:30', 'max:4000']]);
        $service->acknowledge($organisation, $request->user(), $followupId, $input['notes']);

        return response()->json(['message' => 'Review reminder acknowledged; retained data has not been erased.']);
    }
}
