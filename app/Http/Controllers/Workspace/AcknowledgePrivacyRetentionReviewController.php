<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\Social\ManagePrivacyRetentionFollowups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AcknowledgePrivacyRetentionReviewController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, int $followupId,
        ManagePrivacyRetentionFollowups $service): RedirectResponse
    {
        $input = $request->validate(['notes' => ['required', 'string', 'min:30', 'max:4000']]);
        $service->acknowledge($organisation, $request->user(), $followupId, $input['notes']);

        return back()->with('success', 'Reminder acknowledged. The underlying lawful retention decision remains in effect.');
    }
}
