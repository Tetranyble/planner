<?php

namespace App\Console\Commands;

use App\Domain\Publishing\Delivery\SimulationHarness;
use Illuminate\Console\Command;

final class SimulatePublicationDelivery extends Command
{
    protected $signature = 'planner:simulate-publication {provider} {scenario=timeout} {--attempts=1} {--maximum=3} {--verified-idempotency}';

    protected $description = 'Deterministic offline publication simulation; no DB changes or remote calls';

    public function handle(): int
    {
        try {
            $result = SimulationHarness::run((string) $this->argument('provider'), (string) $this->argument('scenario'),
                max(0, (int) $this->option('attempts')), max(0, (int) $this->option('maximum')),
                (bool) $this->option('verified-idempotency'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
