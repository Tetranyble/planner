<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\CreateBulkContentTargetsRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageContentStudio;
use Illuminate\Http\RedirectResponse;

final class BulkContentTargetsController extends Controller
{
    public function __invoke(CreateBulkContentTargetsRequest $request, Organisation $organisation, Brand $brand, string $itemUuid, ManageContentStudio $service): RedirectResponse
    {
        $result = $service->targetsBulk($request->user(), $organisation, $brand, $itemUuid, $request->validated());

        return back()->with('success', $result['created_or_reused'].' platform drafts saved; publishing remains disabled.');
    }
}
