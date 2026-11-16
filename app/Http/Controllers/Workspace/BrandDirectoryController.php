<?php

namespace App\Http\Controllers\Workspace;

use App\Authorization\BrandPermissionGuard;
use App\Http\Controllers\Controller;
use App\Http\Resources\Directory\BrandSummaryResource;
use App\Http\Resources\Directory\OrganisationSummaryResource;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Directory\DirectoryPage;
use App\Services\Directory\ListBrandsService;
use App\Services\Membership\ManageOrganisationMembers;
use App\Services\Onboarding\CreateBrandService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BrandDirectoryController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, ListBrandsService $service, CreateBrandService $creator, ManageOrganisationMembers $members, BrandPermissionGuard $brandGuard): Response
    {
        $perPage = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']])['per_page'] ?? 20;

        $page = $service->execute($request->user(), $organisation, (int) $perPage);
        $accessible = $page->getCollection();
        $strategyReadable = $accessible->filter(fn (Brand $brand) => $brandGuard->allows(
            $request->user(), $brand, 'brand_profile.index'))
            ->pluck('uuid')->values()->all();
        $socialReadable = $accessible->filter(fn (Brand $brand) => $brandGuard->allows(
            $request->user(), $brand, 'social_account.index'))->pluck('uuid')->values()->all();
        $contentReadable = $accessible->filter(fn (Brand $brand) => $brandGuard->allows(
            $request->user(), $brand, 'content_item.index'))->pluck('uuid')->values()->all();

        return Inertia::render('Organisations/Brands', [
            'socialReadableBrandUuids' => $socialReadable,
            'contentReadableBrandUuids' => $contentReadable,
            'strategyReadableBrandUuids' => $strategyReadable,
            'canCreateBrand' => $creator->canCreate($request->user(), $organisation),
            'canManageMembers' => $members->canManage($request->user(), $organisation),
            'organisation' => OrganisationSummaryResource::make($organisation)->resolve(),
            'brands' => DirectoryPage::from(
                $page,
                BrandSummaryResource::class,
            ),
        ]);
    }
}
