<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Domain\Publishing\PublicationReconciliationRules;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Publishing\ReconcilePublications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PublicationReconciliationController extends Controller
{
    public function show(Request $r, Organisation $organisation, Brand $brand, int $jobId, ReconcilePublications $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->history($r->user(), $organisation, $brand, $jobId));
    }

    public function store(Request $r, Organisation $organisation, Brand $brand, int $jobId, ReconcilePublications $service, ApiResponse $response): JsonResponse
    {
        $data = $r->validate(['evidence_kind' => ['required', Rule::in(PublicationReconciliationRules::KINDS)],
            'external_reference' => ['nullable', 'string', 'max:255'], 'evidence_sha256' => ['required', 'regex:/^[a-fA-F0-9]{64}$/'],
            'summary' => ['required', 'string', 'min:12', 'max:2000']]);

        return $response->success($service->submit($r->user(), $organisation, $brand, $jobId, $data));
    }
}
