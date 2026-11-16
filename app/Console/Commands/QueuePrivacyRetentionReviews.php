<?php

namespace App\Console\Commands;

use App\Services\Social\ManagePrivacyRetentionFollowups;
use Illuminate\Console\Command;

final class QueuePrivacyRetentionReviews extends Command
{
    protected $signature = 'planner:queue-privacy-retention-reviews {--limit=50}';

    protected $description = 'Queue due lawful-retention re-reviews without erasing retained records';

    public function handle(ManagePrivacyRetentionFollowups $service): int
    {
        $limit = (int) $this->option('limit');
        if ($limit < 1 || $limit > 200) {
            $this->error('Limit must be 1..200');

            return self::FAILURE;
        }
        $count = $service->enqueueDue($limit);
        $this->info(sprintf('Queued %d due privacy retention reviews.', $count));

        return self::SUCCESS;
    }
}
