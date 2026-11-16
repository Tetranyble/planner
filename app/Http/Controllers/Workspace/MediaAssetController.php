<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\LinkStorageMediaRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageContentStudio;
use Illuminate\Http\RedirectResponse;

final class MediaAssetController extends Controller
{
    public function store(LinkStorageMediaRequest $request, Organisation $organisation, Brand $brand, ManageContentStudio $service): RedirectResponse
    {
        $service->linkMedia($request->user(), $organisation, $brand, (int) $request->validated('storage_media_id'));

        return back()->with('success', 'Content studio saved successfully.');
    }
}
