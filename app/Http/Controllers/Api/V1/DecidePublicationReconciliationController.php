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

final class DecidePublicationReconciliationController extends Controller
{
    public function __invoke(Request $r, Organisation $organisation, Brand $brand, int $jobId, ReconcilePublications $service, ApiResponse $response): JsonResponse
    {
        $data = $r->validate(['evidence_id' => ['required', 'integer', 'min:1'],
            'disposition' => ['required', Rule::in(PublicationReconciliationRules::DISPOSITIONS)],
            'rationale' => ['required', 'string', 'min:12', 'max:2000']]);

        return $response->success($service->decide($r->user(), $organisation, $brand, $jobId, $data));
    }
}
