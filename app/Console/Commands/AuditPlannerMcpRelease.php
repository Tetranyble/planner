<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Server;

/** Release preflight never exposes access tokens, origins, keys or confidential rows. */
final class AuditPlannerMcpRelease extends Command
{
    protected $signature = 'planner:audit-mcp-release {--strict : Fail if any MCP gate is unavailable} {--json : Print sanitized JSON}';

    protected $description = 'Audit explicit read-only MCP release conditions without performing any provider calls';

    public function handle(): int
    {
        $schemas = false;
        try {
            $schemas = Schema::hasTable('personal_access_tokens') && Schema::hasTable('planner_integration_grants');
        } catch (\Throwable) { /* DB unavailable; do not include exception details */
        }
        $gates = [
            'mcp_package_installed' => class_exists(Server::class),
            'mcp_source_registered' => is_file(base_path('routes/ai.php')),
            'integration_read_switch' => (bool) config('planner.integration_read_enabled', false),
            'mcp_read_switch' => (bool) config('planner.mcp_read_enabled', false),
            'https_app_url' => str_starts_with((string) config('app.url'), 'https://'),
            'production_debug_disabled' => ! (bool) config('app.debug', true),
            'required_php_extensions' => extension_loaded('mbstring') && extension_loaded('intl'),
            'token_and_grant_tables_present' => $schemas,
        ];
        $ready = ! in_array(false, $gates, true);
        if ($this->option('json')) {
            $this->line(json_encode(['gates' => $gates, 'ready_for_controlled_testing' => $ready,
                'oauth_delegation_configured' => false, 'live_provider_verified' => false,
                'publishing_authorized' => false], JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Gate', 'Status'], array_map(
                fn ($name, $passed) => [$name, $passed ? 'PASS' : 'BLOCKED'], array_keys($gates), $gates));
            $this->warn('MCP readiness is not live publishing or client OAuth certification.');
        }

        return ($this->option('strict') && ! $ready) ? self::FAILURE : self::SUCCESS;
    }
}
