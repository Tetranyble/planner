<?php

namespace App\Console\Commands;

use App\Services\Publishing\RunPublicationSandbox;
use Illuminate\Console\Command;

final class InspectPublicationSandbox extends Command
{
    protected $signature = 'planner:inspect-publication-sandbox {organisation_id} {brand_id} {publication_job_id} {--limit=30}';

    protected $description = 'Read only, tenant-filtered offline simulation results (privileged local CLI only)';

    public function handle(RunPublicationSandbox $service): int
    {
        $data = $service->history((int) $this->argument('organisation_id'), (int) $this->argument('brand_id'), (int) $this->argument('publication_job_id'), (int) $this->option('limit'));
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
