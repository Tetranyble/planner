<?php

namespace App\Console\Commands;

use App\Services\Social\InspectMetaConnections as Inspection;
use Illuminate\Console\Command;

final class InspectMetaConnections extends Command
{
    protected $signature = 'planner:inspect-meta-connections {--limit=20} {--minutes=60}';

    protected $description = 'Inspect Meta grants and safely downgrade unsupported capabilities';

    public function handle(Inspection $inspection): int
    {
        $limit = (int) $this->option('limit');
        $minutes = (int) $this->option('minutes');
        if ($limit < 1 || $limit > 100 || $minutes < 10 || $minutes > 1440) {
            $this->error('limit must be 1..100 and minutes must be 10..1440');

            return self::FAILURE;
        }
        $r = $inspection->run($limit, $minutes);
        $this->info(sprintf('Checked %d, verified %d, restricted %d, deferred %d, skipped %d.',
            $r['checked'], $r['verified'], $r['restricted'], $r['deferred'], $r['skipped']));

        return self::SUCCESS;
    }
}
