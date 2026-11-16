<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Domain\Publishing\YouTube\CanonicalVideoChunkSourceContract;
use App\Domain\Publishing\YouTube\UnavailableVideoChunkSource;
use App\Http\Controllers\Controller;
use App\Services\Publishing\ProcessYouTubeTransfers;
use Illuminate\Http\JsonResponse;

final class YouTubeTransferReleaseController extends Controller
{
    public function __invoke(ApiResponse $response, CanonicalVideoChunkSourceContract $reader): JsonResponse
    {
        return $response->success([
            'dispatch_enabled' => ProcessYouTubeTransfers::enabled() && ! ($reader instanceof UnavailableVideoChunkSource),
            'storage_reader_verified' => ! ($reader instanceof UnavailableVideoChunkSource),
            'release_blockers' => array_values(array_filter([
                ProcessYouTubeTransfers::enabled() ? null : 'PRODUCTION_DISPATCH_NOT_RELEASED',
                $reader instanceof UnavailableVideoChunkSource ? 'TETRANYBLE_STORAGE_BYTE_SOURCE_NOT_VERIFIED' : null,
                (bool) config('planner.media_entitlements.youtube', false) ? null : 'YOUTUBE_APP_PERMISSION_NOT_VERIFIED',
                'REAL_GOOGLE_UPLOAD_AND_MYSQL_TEST_EVIDENCE_PENDING',
            ])),
        ]);
    }
}
