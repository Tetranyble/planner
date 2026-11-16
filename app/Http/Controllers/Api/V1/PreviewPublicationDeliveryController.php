<?php

namespace App\Http\Controllers\Api\V1;

use App\Authorization\BrandPermissionGuard;
use App\Common\ApiResponse;
use App\Domain\Publishing\PublicationDeliveryPlan;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PreviewPublicationDeliveryController extends Controller
{
    public function __invoke(Request $r, Organisation $organisation, Brand $brand, BrandPermissionGuard $guard, ApiResponse $response): JsonResponse
    {
        if ((int) $brand->organisation_id !== (int) $organisation->id || ! $guard->allows($r->user(), $brand, 'approval_request.index')) {
            throw new AuthorizationException('Publication planning access denied.');
        }
        $data = $r->validate(['provider' => ['required', 'string', 'max:30'], 'media_kind' => ['required', 'string', 'max:30'],
            'has_credential' => ['required', 'boolean'], 'has_approval' => ['required', 'boolean']]);

        return $response->success(PublicationDeliveryPlan::preview($data['provider'], $data['media_kind'],
            $data['has_credential'], $data['has_approval']));
    }
}
