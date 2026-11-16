<?php

namespace App\Console\Commands;

use App\Services\Publishing\RunPublicationSandbox;
use Illuminate\Console\Command;

final class ProcessPublicationSandbox extends Command
{
    protected $signature = 'planner:process-publication-sandbox {--limit=20}';

    protected $description = 'Process queued publication simulations only; never connect to a provider';

    public function handle(RunPublicationSandbox $service): int
    {
        $this->line(json_encode($service->process((int) $this->option('limit')), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
