<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\CreateContentItemRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageContentStudio;
use Illuminate\Http\RedirectResponse;

final class ContentItemController extends Controller
{
    public function store(CreateContentItemRequest $request, Organisation $organisation, Brand $brand, ManageContentStudio $service): RedirectResponse
    {
        $service->create($request->user(), $organisation, $brand, $request->validated());

        return back()->with('success', 'Content studio saved successfully.');
    }
}
