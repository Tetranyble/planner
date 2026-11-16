<?php

namespace App\Console\Commands;

use App\Models\CreativeImageHandoff;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Delete stale provenance claims only. No images, Storage objects, or published assets are touched. */
final class PruneCreativeImageHandoffs extends Command
{
    protected $signature = 'planner:prune-creative-handoffs {--limit=100 : Maximum metadata records per run} {--dry-run : Only report candidates}';

    protected $description = 'Prune expired unused creative claims after seven days; retain linked evidence for 90 days';

    public function handle(): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $ids = CreativeImageHandoff::query()
            ->where(function ($query): void {
                $query->where(function ($q): void {
                    $q->where('state', 'AWAITING_UPLOAD')->where('expires_at', '<', now()->subDays(7));
                })->orWhere(function ($q): void {
                    $q->where('state', 'LINKED')->where('linked_at', '<', now()->subDays(90));
                });
            })->orderBy('id')->limit($limit)->pluck('id')->all();
        if ($this->option('dry-run')) {
            $this->info('Creative claim metadata eligible for pruning: '.count($ids));

            return self::SUCCESS;
        }
        $deleted = 0;
        foreach ($ids as $id) {
            $deleted += DB::transaction(function () use ($id): int {
                $claim = CreativeImageHandoff::query()->whereKey($id)->lockForUpdate()->first();
                if (! $claim) {
                    return 0;
                }
                $expiredUnused = $claim->state === 'AWAITING_UPLOAD' && $claim->expires_at->lt(now()->subDays(7));
                $expiredLinked = $claim->state === 'LINKED'
                    && $claim->linked_at !== null && $claim->linked_at->lt(now()->subDays(90));
                if (! $expiredUnused && ! $expiredLinked) {
                    return 0;
                }
                $claim->delete();

                return 1;
            });
        }
        $this->info("Pruned {$deleted} creative claim metadata rows; Storage media and audit logs untouched.");

        return self::SUCCESS;
    }
}
