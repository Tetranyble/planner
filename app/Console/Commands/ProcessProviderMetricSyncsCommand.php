<?php

namespace App\Console\Commands;

use App\Services\Analytics\ProcessProviderMetricSyncs;
use Illuminate\Console\Command;

/** No network operations or DB writes when provider metric sync flag is disabled. */
final class ProcessProviderMetricSyncsCommand extends Command
{
    protected $signature = 'planner:sync-provider-metrics {--limit=20}';

    protected $description = 'Read-only provider-confirmed YouTube metrics sync; disabled by default';

    public function handle(ProcessProviderMetricSyncs $service): int
    {
        $result = $service->process((int) $this->option('limit'));
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
