<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Publishing\MediaTikTokCreatorConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConsentTikTokMediaRunController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, int $runId,
        MediaTikTokCreatorConsent $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validate([
            'privacy_level' => ['required', 'string', 'in:PUBLIC_TO_EVERYONE,MUTUAL_FOLLOW_FRIENDS,FOLLOWER_OF_CREATOR,SELF_ONLY'],
            'disable_comment' => ['required', 'boolean'], 'disable_duet' => ['required', 'boolean'],
            'disable_stitch' => ['required', 'boolean'], 'is_aigc' => ['required', 'boolean'],
            'explicit_consent' => ['required', 'accepted'],
        ]);

        return $response->success($service->consent($request->user(), $organisation, $brand, $runId, $data));
    }
}
