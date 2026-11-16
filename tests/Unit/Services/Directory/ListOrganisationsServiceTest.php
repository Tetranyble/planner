<?php

namespace Tests\Unit\Services\Directory;

use App\Models\User;
use App\Repository\Contracts\OrganisationDirectoryRepositoryContract;
use App\Services\Directory\ListOrganisationsService;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;

final class ListOrganisationsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_delegates_the_authenticated_user_and_requested_page_size(): void
    {
        $actor = new User;
        $actor->id = 7;
        $actor->exists = true;
        $page = new LengthAwarePaginator([], 0, 15, 1);
        $repository = Mockery::mock(OrganisationDirectoryRepositoryContract::class);
        $repository->shouldReceive('paginateActiveForMember')->once()->with($actor, 15)->andReturn($page);

        self::assertSame($page, (new ListOrganisationsService($repository))->execute($actor, 15));
    }

    public function test_rejects_page_sizes_outside_the_documented_limit(): void
    {
        $repository = Mockery::mock(OrganisationDirectoryRepositoryContract::class);
        $repository->shouldNotReceive('paginateActiveForMember');
        $actor = new User;
        $actor->exists = true;
        $this->expectException(InvalidArgumentException::class);
        (new ListOrganisationsService($repository))->execute($actor, 101);
    }

    public function test_rejects_unsaved_user_before_repository_access(): void
    {
        $repository = Mockery::mock(OrganisationDirectoryRepositoryContract::class);
        $repository->shouldNotReceive('paginateActiveForMember');
        $this->expectException(InvalidArgumentException::class);
        (new ListOrganisationsService($repository))->execute(new User);
    }
}
