<?php

namespace Tests\Feature\Platform;

use App\Repository\BaseRepository;
use App\Repository\BrandScopedRepository;
use App\Repository\Contracts\CampaignRepositoryContract;
use App\Repository\Contracts\UserRepositoryContract;
use App\Repository\Eloquent\CampaignRepository;
use App\Repository\Eloquent\UserRepository;
use Tests\TestCase;

final class RepositoryContractBindingsTest extends TestCase
{
    public function test_bindings_resolve_the_expected_implementations(): void
    {
        $this->assertInstanceOf(UserRepository::class, app(UserRepositoryContract::class));
        $this->assertInstanceOf(CampaignRepository::class, app(CampaignRepositoryContract::class));
    }

    public function test_brand_scoped_repository_cannot_inherit_unscoped_accessors(): void
    {
        $repository = app(CampaignRepositoryContract::class);
        $this->assertInstanceOf(BrandScopedRepository::class, $repository);
        $this->assertNotInstanceOf(BaseRepository::class, $repository);
        foreach (['all', 'find', 'count', 'query', 'updateOrCreate', 'findBy'] as $unsafe) {
            $this->assertFalse(method_exists($repository, $unsafe), "Unsafe accessor {$unsafe} must not be exposed.");
        }
    }

    public function test_user_repository_does_not_inherit_broken_save_signature(): void
    {
        $this->assertFalse(method_exists(app(UserRepositoryContract::class), 'save'));
    }
}
