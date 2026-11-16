<?php

namespace Modules\Unipotence\Infrastructure\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Unipotence\Domain\Enums\UnipotenceState;
use Modules\Unipotence\Infrastructure\Persistence\Models\Unipotence;

#[Signature('unipotence:prune {--days= : Override the configured retention period}')]
#[Description('Prune completed unipotence records and quarantine abandoned claims')]
final class PruneUnipotences extends Command
{
    public function handle(): int
    {
        $configuredDays = (int) config('unipotence.retention_days');
        $daysOption = $this->option('days');
        $days = $daysOption === null ? $configuredDays : (int) $daysOption;

        if ($days < 1) {
            $this->error('Retention must be at least one day.');

            return self::INVALID;
        }

        $cutoff = now()->subDays($days);
        $indeterminateCutoff = now()->subDays(
            (int) config('unipotence.indeterminate_retention_days'),
        );

        $connection = (string) config('unipotence.connection');

        $completed = Unipotence::on($connection)
            ->where('state', UnipotenceState::Completed)
            ->where('completed_at', '<=', $cutoff)
            ->delete();

        $indeterminate = Unipotence::on($connection)
            ->where('state', UnipotenceState::Indeterminate)
            ->where('indeterminate_at', '<=', $indeterminateCutoff)
            ->delete();

        $quarantined = Unipotence::on($connection)
            ->where('state', UnipotenceState::Claimed)
            ->where('locked_until', '<=', $cutoff)
            ->update([
                'state' => UnipotenceState::Indeterminate,
                'indeterminate_at' => now(),
                'updated_at' => now(),
            ]);

        $this->info(
            "Pruned {$completed} completed and {$indeterminate} indeterminate records; "
            ."quarantined {$quarantined} abandoned claims.",
        );

        return self::SUCCESS;
    }
}
