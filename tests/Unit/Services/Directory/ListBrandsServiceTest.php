<?php

namespace Tests\Unit\Services\Directory;

use App\Authorization\CurrentOrganisation;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\BrandDirectoryRepositoryContract;
use App\Services\Directory\ListBrandsService;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;

final class ListBrandsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rejects_unbounded_page_size_before_accessing_repository(): void
    {
        $repository = Mockery::mock(BrandDirectoryRepositoryContract::class);
        $repository->shouldNotReceive('paginateActiveForMember');

        $this->expectException(InvalidArgumentException::class);
        (new ListBrandsService($repository, new CurrentOrganisation))
            ->execute(new User, new Organisation, 101);
    }

    public function test_unsaved_user_cannot_access_the_repository(): void
    {
        $repository = Mockery::mock(BrandDirectoryRepositoryContract::class);
        $repository->shouldNotReceive('paginateActiveForMember');

        $this->expectException(AuthorizationException::class);
        (new ListBrandsService($repository, new CurrentOrganisation))
            ->execute(new User, new Organisation, 20);
    }
}
