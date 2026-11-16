<?php

namespace App\Console\Commands;

use App\Repository\Contracts\YouTubeTransferRepositoryContract;
use Illuminate\Console\Command;

final class RecoverYouTubeTransfersCommand extends Command
{
    protected $signature = 'planner:recover-youtube-transfers {--limit=30}';

    protected $description = 'Quarantine stale YouTube stage leases; never replay an unknown upload';

    public function handle(YouTubeTransferRepositoryContract $repo): int
    {
        $this->line(json_encode($repo->recover(max(1, min(100, (int) $this->option('limit'))))));

        return self::SUCCESS;
    }
}
