<?php

namespace App\Http\Controllers\Api\V1;

use App\Authorization\BrandPermissionGuard;
use App\Common\ApiResponse;
use App\Domain\Publishing\Media\MediaPublicationProtocols;
use App\Domain\Publishing\Media\MediaReleaseRequirements;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MediaPublicationReleaseController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand,
        BrandPermissionGuard $guard, ApiResponse $response): JsonResponse
    {
        if ((int) $brand->organisation_id !== (int) $organisation->id
            || ! $guard->allows($request->user(), $brand, 'approval_request.index')) {
            throw new AuthorizationException('Media publishing release inspection denied.');
        }

        return $response->success(array_map(fn (string $provider) => MediaReleaseRequirements::checklist(
            $provider, (bool) config('planner.media_entitlements.'.$provider, false),
            (bool) config('planner.media_delivery_origin_verified', false),
            (bool) config('planner.media_dispatch_released', false)
        ), MediaPublicationProtocols::PROVIDERS));
    }
}
