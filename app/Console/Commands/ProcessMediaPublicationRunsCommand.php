<?php

namespace App\Console\Commands;

use App\Services\Publishing\ProcessMediaPublicationRuns;
use Illuminate\Console\Command;

final class ProcessMediaPublicationRunsCommand extends Command
{
    protected $signature = 'planner:process-media-publications {--limit=15}';

    protected $description = 'Run only operator-armed, release-gated, lease-fenced Instagram media stages';

    public function handle(ProcessMediaPublicationRuns $service): int
    {
        $this->line(json_encode($service->process(max(1, min(50, (int) $this->option('limit')))), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
