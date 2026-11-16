<?php

namespace App\Console\Commands;

use App\Services\Analytics\ManageProviderMetricSyncSchedules;
use Illuminate\Console\Command;

final class QueueProviderMetricSyncsCommand extends Command
{
    protected $signature = 'planner:queue-provider-metrics {--limit=20}';

    protected $description = 'Queue opted-in, brand-scoped daily YouTube metric observations; no provider calls';

    public function handle(ManageProviderMetricSyncSchedules $service): int
    {
        $this->line(json_encode($service->queueDue((int) $this->option('limit')), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
