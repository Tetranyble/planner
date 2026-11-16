<?php

namespace Tests\Unit\Services\Onboarding;

use App\Models\Organisation;
use App\Models\User;
use App\Services\Onboarding\CreateBrandService;
use App\Services\Onboarding\CreateOrganisationService;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

/** Fast, database-free checks that no write can start with an unsaved principal. */
final class OnboardingGuardsTest extends TestCase
{
    public function test_organisation_service_rejects_an_unsaved_user_before_any_persistence(): void
    {
        $this->expectException(AuthorizationException::class);
        app(CreateOrganisationService::class)->execute(new User, 'A workspace', 'Africa/Lagos');
    }

    public function test_brand_service_rejects_an_unsaved_principal(): void
    {
        $organisation = new Organisation(['name' => 'Example']);
        $this->assertFalse(app(CreateBrandService::class)->canCreate(new User, $organisation));
    }
}
