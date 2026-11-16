<?php

namespace App\Console\Commands;

use App\Services\Publishing\RunPublicationSandbox;
use Illuminate\Console\Command;

final class QueuePublicationSandbox extends Command
{
    protected $signature = 'planner:queue-publication-sandbox {organisation_id} {brand_id} {publication_job_id} {provider} {scenario} {operation_key}';

    protected $description = 'Queue a deterministic offline simulation for a held publication (privileged local CLI only)';

    public function handle(RunPublicationSandbox $service): int
    {
        try {
            $result = $service->enqueue((int) $this->argument('organisation_id'), (int) $this->argument('brand_id'),
                (int) $this->argument('publication_job_id'), (string) $this->argument('operation_key'),
                (string) $this->argument('provider'), (string) $this->argument('scenario'));
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Unable to queue simulation: '.class_basename($e));

            return self::FAILURE;
        }
    }
}
