<?php

namespace App\Console\Commands;

use App\Services\Publishing\ProcessYouTubeTransfers;
use Illuminate\Console\Command;

final class ProcessYouTubeTransfersCommand extends Command
{
    protected $signature = 'planner:process-youtube-transfers {--limit=10}';

    protected $description = 'Process explicitly released, fenced YouTube resumable upload stages; disabled by default';

    public function handle(ProcessYouTubeTransfers $worker): int
    {
        $this->line(json_encode($worker->process(max(1, min(30, (int) $this->option('limit'))))));

        return self::SUCCESS;
    }
}
