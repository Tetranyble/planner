<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\ManageBrandStrategy;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BrandStrategyController extends Controller
{
    public function show(Request $request, Organisation $organisation, Brand $brand, ManageBrandStrategy $service): Response
    {
        return Inertia::render('Organisations/Brands/Strategy', [
            'organisation' => ['uuid' => $organisation->uuid, 'name' => $organisation->name],
            'brand' => ['uuid' => $brand->uuid, 'name' => $brand->name],
            'strategy' => $service->read($request->user(), $organisation, $brand),
            'can' => [
                'profile' => $service->can($request->user(), $organisation, $brand, 'brand_profile.update'),
                'audience' => $service->can($request->user(), $organisation, $brand, 'brand_audience_segment.store'),
                'voice' => $service->can($request->user(), $organisation, $brand, 'brand_voice_profile.store'),
                'claimPolicy' => $service->can($request->user(), $organisation, $brand, 'brand_claim_policy.update'),
                'claim' => $service->can($request->user(), $organisation, $brand, 'brand_claim.store'),
                'approvalPolicy' => $service->can($request->user(), $organisation, $brand, 'brand_approval_policy.store'),
                'sourceCreate' => $service->can($request->user(), $organisation, $brand, 'brand_knowledge_source.store'),
                'sourceReview' => $service->can($request->user(), $organisation, $brand, 'knowledge-source.verify'),
                'evidenceCreate' => $service->can($request->user(), $organisation, $brand, 'brand_claim_evidence.store'),
                'evidenceReview' => $service->can($request->user(), $organisation, $brand, 'claim-evidence.verify'),
                'claimSubmit' => $service->can($request->user(), $organisation, $brand, 'claim.submit-review'),
                'claimReview' => $service->can($request->user(), $organisation, $brand, 'claim.review'),
                'policyActivate' => $service->can($request->user(),$organisation,$brand,'approval-policy.activate'),
            ],
        ]);
    }
}
