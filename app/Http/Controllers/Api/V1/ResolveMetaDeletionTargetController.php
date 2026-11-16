<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\Social\ReviewMetaDataDeletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ResolveMetaDeletionTargetController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, int $targetId,
        ReviewMetaDataDeletion $service): JsonResponse
    {
        $data = $request->validate([
            'disposition' => ['required', 'in:CLEARED,RETAINED_LEGAL'],
            'explanation' => ['required', 'string', 'min:30', 'max:4000'],
            'evidence_reference' => ['required', 'string', 'min:8', 'max:512'],
            'legal_basis' => ['nullable', 'in:LEGAL_OBLIGATION,LEGAL_CLAIMS,REGULATORY_HOLD'],
            'review_again_at' => ['nullable', 'date_format:Y-m-d', 'after:today'],
        ]);
        $service->resolve($organisation, $request->user(), $targetId, $data);

        return response()->json(['message' => 'Privacy review decision recorded.']);
    }
}
