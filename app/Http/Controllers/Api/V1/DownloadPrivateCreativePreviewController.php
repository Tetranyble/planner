<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Creative\CanonicalCreativeImageUpload;
use App\Services\Creative\DownloadPrivateCreativePreview;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class DownloadPrivateCreativePreviewController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, DownloadPrivateCreativePreview $service, CanonicalCreativeImageUpload $canonical): Response
    {
        $input = $request->validate([
            'prompt' => ['required', 'string', 'min:20', 'max:1400'],
            'size' => ['required', Rule::in(['1024x1024', '1536x1024', '1024x1536'])],
            'auto_store' => ['sometimes', 'boolean'],
        ]);
        // Reject unreleased ingest BEFORE an expensive provider call.
        $autoStore = (bool) ($input['auto_store'] ?? false);
        if ($autoStore && ! CanonicalCreativeImageUpload::isReleased()) {
            abort(403, 'Canonical creative ingestion is disabled.');
        }
        $image = $service->create($request->user(), $organisation, $brand, $input['prompt'], $input['size']);
        $mediaId = null;
        $ingestState = $autoStore ? 'FAILED' : 'NOT_REQUESTED';
        if ($autoStore) {
            try {
                $mediaId = $canonical->upload($request->user(), $organisation, $brand, $image);
                $ingestState = 'PENDING_STORAGE_REVIEW';
            } catch (\Throwable $error) {
                // Provider generation was already charged; retain the private PNG
                // rather than losing it and encouraging an accidental paid retry.
                report($error);
            }
        }

        return response($image['bytes'], 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="planner-private-creative.png"',
            'Content-Length' => (string) strlen($image['bytes']),
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Generated-Image-SHA256' => $image['sha256'],
            'X-Canonical-Media' => 'false',
            'X-Creative-Handoff' => $image['handoff_uuid'],
            'X-Creative-Handoff-Expires-At' => $image['expires_at'],
            'X-Creative-Storage-Ingest-State' => $ingestState,
            'X-Creative-Storage-Media-ID' => $mediaId === null ? '' : (string) $mediaId,
        ]);
    }
}
