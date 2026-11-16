<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Analytics\ManageSocialMetricSnapshots;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SocialMetricSnapshotController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand,
        ManageSocialMetricSnapshots $service, ApiResponse $response): JsonResponse
    {
        $values = $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d']]);

        return $response->success($service->report($request->user(), $organisation, $brand, $values['from'], $values['to']));
    }

    public function store(Request $request, Organisation $organisation, Brand $brand,
        ManageSocialMetricSnapshots $service, ApiResponse $response): JsonResponse
    {
        $values = $request->validate([
            'provider' => ['required', 'in:facebook,instagram,linkedin,x,tiktok,youtube'],
            'account_reference' => ['required', 'string', 'max:160', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'external_post_reference' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'observed_at' => ['required', 'date'],
            'impressions' => ['sometimes', 'integer', 'min:0', 'max:1000000000000'],
            'reach' => ['sometimes', 'integer', 'min:0', 'max:1000000000000'],
            'likes' => ['sometimes', 'integer', 'min:0', 'max:1000000000000'],
            'comments' => ['sometimes', 'integer', 'min:0', 'max:1000000000000'],
            'shares' => ['sometimes', 'integer', 'min:0', 'max:1000000000000'],
            'clicks' => ['sometimes', 'integer', 'min:0', 'max:1000000000000'],
            'source_type' => ['required', 'in:MANUAL_EXPORT,PROVIDER_SCREENSHOT'],
            'source_reference' => ['required', 'string', 'max:240'],
            'submission_key' => ['required', 'string', 'min:16', 'max:128'],
        ]);

        return $response->success($service->submit($request->user(), $organisation, $brand, $values));
    }
}
