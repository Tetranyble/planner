<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\CreateVoiceRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\ManageBrandStrategy;
use Illuminate\Http\RedirectResponse;

final class BrandVoiceProfileController extends Controller
{
    public function store(CreateVoiceRequest $request, Organisation $organisation, Brand $brand, ManageBrandStrategy $service): RedirectResponse
    {
        $service->addVoice($request->user(), $organisation, $brand, $request->validated());

        return back()->with('success', 'Voice profile created.');
    }
}
