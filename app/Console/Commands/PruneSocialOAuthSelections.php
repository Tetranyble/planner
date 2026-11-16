<?php

namespace App\Console\Commands;

use App\Repository\Contracts\SocialOAuthSelectionRepositoryContract;
use Illuminate\Console\Command;

final class PruneSocialOAuthSelections extends Command
{
    protected $signature = 'planner:prune-social-oauth-selections';

    protected $description = 'Remove expired or consumed encrypted OAuth account-selection grants.';

    public function handle(SocialOAuthSelectionRepositoryContract $selections): int
    {
        $count = $selections->pruneExpired();
        $this->info("Pruned {$count} expired OAuth selections.");

        return self::SUCCESS;
    }
}
