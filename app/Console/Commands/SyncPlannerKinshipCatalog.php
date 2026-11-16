<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use Illuminate\Console\Command;
use Tetranyble\Kinship\Catalog\PermissionCatalogSeeder;

/** Explicit additive roll-forward for existing organisations after permission-catalog changes. */
final class SyncPlannerKinshipCatalog extends Command
{
    protected $signature = 'planner:sync-kinship-catalog {--dry-run : Show work without database changes} {--sync : Reconcile built-in role grants to the catalog; remove retired grants}';

    protected $description = 'Add new Planner permissions and role grants in each existing Kinship workspace.';

    public function handle(PermissionCatalogSeeder $catalog): int
    {
        $count = 0;
        Organisation::query()->orderBy('id')->chunkById(50, function ($organisations) use ($catalog, &$count): void {
            foreach ($organisations as $organisation) {
                $catalog->seed((int) $organisation->getKey(), workspaceScoped: true,
                    sync: (bool) $this->option('sync'), dryRun: (bool) $this->option('dry-run'));
                $count++;
            }
        });
        $this->info("Processed {$count} organisation catalog(s). Role grants synced when --sync is supplied; custom roles were not deleted.");

        return self::SUCCESS;
    }
}
