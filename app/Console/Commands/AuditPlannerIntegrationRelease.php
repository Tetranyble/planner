<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Server;

/** Read-only release preflight. No secrets, tokens, actors or provider calls are emitted. */
final class AuditPlannerIntegrationRelease extends Command
{
    protected $signature = 'planner:audit-integration-release {--json : Print sanitized JSON} {--strict : Fail unless all read-only integration gates pass}';

    protected $description = 'Assess HTTPS, token storage, tenancy and external read switch without issuing credentials';

    public function handle(): int
    {
        $isHttps = str_starts_with((string) config('app.url'), 'https://');
        $connectionReady = false;
        $grantsTableReady = false;
        $tokensTableReady = false;
        try {
            $grantsTableReady = Schema::hasTable('planner_integration_grants');
            $tokensTableReady = Schema::hasTable('personal_access_tokens');
            $connectionReady = true;
        } catch (\Throwable) {
            // No database status details are exposed through diagnostic output.
        }
        $gates = [
            'integration_read_enabled' => (bool) config('planner.integration_read_enabled', false),
            'https_application_url' => $isHttps,
            'application_debug_disabled' => (bool) ! config('app.debug', true),
            'database_reachable' => $connectionReady,
            'sanctum_token_store_exists' => $tokensTableReady,
            'integration_grant_store_exists' => $grantsTableReady,
            'php_mbstring_available' => extension_loaded('mbstring'),
        ];
        $ready = ! in_array(false, $gates, true);
        if ($this->option('json')) {
            $this->line(json_encode(['gates' => $gates, 'ready_for_controlled_read_testing' => $ready,
                'publishing_access' => false, 'mcp_server_installed' => class_exists(Server::class),
                'external_provider_access_verified' => false], JSON_THROW_ON_ERROR));
        } else {
            $rows = [];
            foreach ($gates as $gate => $enabled) {
                $rows[] = [$gate, $enabled ? 'PASS' : 'BLOCKED'];
            }
            $this->table(['Gate', 'Status'], $rows);
            $this->comment('Read-only local audit. It does not execute integration tools or verify OAuth/delegated consent.');
        }

        return ($this->option('strict') && ! $ready) ? self::FAILURE : self::SUCCESS;
    }
}
