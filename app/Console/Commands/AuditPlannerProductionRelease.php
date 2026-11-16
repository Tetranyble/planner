<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Server;
use Laravel\Passport\Passport;
use Tetranyble\Storage\Http\Adapters\LaravelIncomingFile;
use Tetranyble\Storage\Modules\Media\Application\UploadMedia;
use Tetranyble\Storage\Modules\Storage\Application\Contracts\FileSystemContract;

/** Read-only deployment gates; never executes providers, changes DB or leaks configuration values. */
final class AuditPlannerProductionRelease extends Command
{
    protected $signature = 'planner:audit-production-release {--strict : Return failure if any mandatory gate fails} {--json : Print sanitized JSON}';

    protected $description = 'Audit production prerequisites and disabled unsafe integrations';

    public function handle(): int
    {
        $db = false;
        try {
            $required = ['publication_jobs', 'publication_attempts', 'published_posts', 'media_publication_runs',
                'youtube_resumable_transfers', 'planner_integration_grants', 'planner_operational_alerts',
                'planner_alert_mail_outbox', 'brand_ai_usage_reservations', 'recurring_campaign_occurrences', 'creative_image_handoffs'];
            $db = collect($required)->every(fn ($table) => Schema::hasTable($table));
        } catch (\Throwable) {
            $db = false;
        }
        $mcpInstalled = class_exists(Server::class);
        $safeFlags = (bool) config('planner.emergency_publish_stop', true)
            && ! config('planner.text_dispatch_released', false)
            && ! config('planner.media_dispatch_released', false)
            && ! config('planner.youtube_resumable_enabled', false);
        $gates = [
            'production_environment' => app()->environment('production'),
            'https_app_url' => str_starts_with((string) config('app.url'), 'https://'),
            'debug_disabled' => ! (bool) config('app.debug', true),
            'app_key_present' => is_string(config('app.key')) && strlen((string) config('app.key')) > 20,
            'php_required_extensions' => extension_loaded('pdo_mysql') && extension_loaded('mbstring') && extension_loaded('intl'),
            'database_schema_available' => $db,
            'queue_not_sync' => ! in_array((string) config('queue.default'), ['sync', 'null', ''], true),
            'mcp_package_installed' => $mcpInstalled,
            'passport_package_installed' => class_exists(Passport::class),
            'creative_upload_contract_present' => class_exists(UploadMedia::class)
                && class_exists(LaravelIncomingFile::class),
            'creative_ingest_release_coherent' => ! config('planner.creative_auto_storage_enabled', false)
                || (config('planner.creative_image_enabled', false) && config('planner.storage_v3_adapter_verified', false)),
            'storage_v3_package_contract' => interface_exists(FileSystemContract::class),
            'unverified_live_posting_disabled' => $safeFlags,
        ];
        $ready = ! in_array(false, $gates, true);
        $result = ['ready_for_restricted_deployment_testing' => $ready, 'gates' => $gates,
            'live_social_provider_certified' => false, 'storage_media_delivery_certified' => false,
            'privacy_legal_pages_deployed' => false, 'full_end_to_end_tests_executed_here' => false];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Gate', 'Result'], array_map(fn ($key, $ok) => [$key, $ok ? 'PASS' : 'BLOCKED'], array_keys($gates), $gates));
        }

        return $this->option('strict') && ! $ready ? self::FAILURE : self::SUCCESS;
    }
}
