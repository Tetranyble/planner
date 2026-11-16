<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\CreateContentTargetRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageContentStudio;
use Illuminate\Http\RedirectResponse;

final class ContentTargetController extends Controller
{
    public function store(CreateContentTargetRequest $request, Organisation $organisation, Brand $brand, string $itemUuid, ManageContentStudio $service): RedirectResponse
    {
        $service->target($request->user(), $organisation, $brand, $itemUuid, $request->validated());

        return back()->with('success', 'Content studio saved successfully.');
    }
}
