<?php

namespace App\Http\Controllers\Api\V1;

use App\Authorization\BrandPermissionGuard;
use App\Common\ApiResponse;
use App\Domain\Publishing\Delivery\SimulationHarness;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Operator diagnostic only: does not read accounts, persist attempts, or contact providers. */
final class SimulatePublicationDeliveryController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand,
        BrandPermissionGuard $guard, ApiResponse $response): JsonResponse
    {
        if ((int) $brand->organisation_id !== (int) $organisation->id ||
            ! $guard->allows($request->user(), $brand, 'approval_request.index')) {
            throw new AuthorizationException('Publication diagnostic access denied.');
        }
        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in(['facebook', 'instagram', 'linkedin', 'x', 'tiktok', 'youtube'])],
            'scenario' => ['required', 'string', Rule::in(SimulationHarness::SCENARIOS)],
            'attempts' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'maximum' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'idempotency_verified' => ['sometimes', 'boolean'],
        ]);

        return $response->success(SimulationHarness::run($data['provider'], $data['scenario'],
            $data['attempts'] ?? 1, $data['maximum'] ?? 3, $data['idempotency_verified'] ?? false));
    }
}
