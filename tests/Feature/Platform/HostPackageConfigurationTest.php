<?php

namespace Tests\Feature\Platform;

use App\Authorization\CurrentOrganisationKinshipResolver;
use App\Models\Organisation;
use App\Models\User;
use Tests\TestCase;
use Tetranyble\Kinship\Contracts\Workspace;
use Tetranyble\Kinship\Contracts\WorkspaceSubject;
use Tetranyble\Storage\Modules\Workspace\Application\Contracts\StorageUser;

class HostPackageConfigurationTest extends TestCase
{
    public function test_kinship_workspace_is_application_organisation_and_is_enabled(): void
    {
        $this->assertSame(Organisation::class, config('kinship.models.workspace'));
        $this->assertTrue(config('kinship.workspace.enabled'));
        $this->assertSame(CurrentOrganisationKinshipResolver::class, config('kinship.workspace.resolver'));
        $this->assertTrue(is_subclass_of(Organisation::class, Workspace::class));
        $this->assertTrue(is_subclass_of(User::class, WorkspaceSubject::class));
        $this->assertTrue(config('kinship.catalog.discovery.enabled'));
    }

    public function test_storage_package_uses_host_user_and_organisation_tables(): void
    {
        $this->assertSame(Organisation::class, config('tetranyble-storage.models.workspace'));
        $this->assertSame(User::class, config('tetranyble-storage.models.user'));
        $this->assertSame('organisations', config('tetranyble-storage.database.tables.workspaces'));
        $this->assertTrue(is_subclass_of(User::class, StorageUser::class));
        $this->assertFalse(config('tetranyble-storage.routes.enabled'));
    }
}
