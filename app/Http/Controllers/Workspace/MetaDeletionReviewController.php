<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\Social\ManagePrivacyRetentionFollowups;
use App\Services\Social\ReviewMetaDataDeletion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class MetaDeletionReviewController extends Controller
{
    public function index(Request $request, Organisation $organisation, ReviewMetaDataDeletion $service, ManagePrivacyRetentionFollowups $retention): Response
    {
        return Inertia::render('Organisations/Privacy/DeletionReviews', [
            'organisation' => $organisation->only(['uuid', 'name']),
            'targets' => $service->directory($organisation, $request->user()),
            'retentionFollowups' => $retention->directory($organisation, $request->user()),
        ]);
    }
}
