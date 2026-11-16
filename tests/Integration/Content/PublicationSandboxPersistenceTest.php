<?php

namespace Tests\Integration\Content;

use App\Services\Publishing\RunPublicationSandbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Requires a disposable MySQL 8 database with the complete application migrations. */
final class PublicationSandboxPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sandbox_tables_and_constraints_exist(): void
    {
        $this->assertTrue(Schema::hasTable('publication_sandbox_runs'));
        $this->assertTrue(Schema::hasTable('publication_sandbox_events'));
        $this->assertTrue(Schema::hasColumns('publication_sandbox_runs', ['organisation_id', 'brand_id', 'publication_job_id', 'operation_key', 'status', 'lease_token']));
    }

    public function test_worker_with_no_runs_does_not_touch_publications(): void
    {
        $before = DB::table('publication_jobs')->count();
        $result = app(RunPublicationSandbox::class)->process(10);
        $this->assertSame(['completed' => 0, 'quarantined_or_failed' => 0, 'network_requests' => 0, 'live_publications' => 0], $result);
        $this->assertSame($before, DB::table('publication_jobs')->count());
    }
}
