<?php

namespace Modules\Unipotence\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Unipotence\Application\UnipotenceGate;
use Modules\Unipotence\Domain\Contracts\FailureClassifier;
use Modules\Unipotence\Domain\Contracts\UnipotenceRepository;
use Modules\Unipotence\Domain\Services\OutcomePolicy;
use Modules\Unipotence\Infrastructure\Console\Commands\PruneUnipotences;
use Modules\Unipotence\Infrastructure\Http\HttpFailureClassifier;
use Modules\Unipotence\Infrastructure\Http\UnipotenceHttpAdapter;
use Modules\Unipotence\Infrastructure\Persistence\DatabaseUnipotenceRepository;
use Psr\Log\LoggerInterface;

final class UnipotenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../Config/unipotence.php',
            'unipotence',
        );

        $this->app->singleton(UnipotenceRepository::class, fn ($app): UnipotenceRepository => new DatabaseUnipotenceRepository(
            connectionName: (string) config('unipotence.connection'),
            logger: $app->make(LoggerInterface::class),
        ));
        $this->app->singleton(FailureClassifier::class, HttpFailureClassifier::class);

        $this->app->bind(UnipotenceGate::class, fn ($app): UnipotenceGate => new UnipotenceGate(
            repository: $app->make(UnipotenceRepository::class),
            outcomePolicy: $app->make(OutcomePolicy::class),
            leaseSeconds: (int) config('unipotence.lease_seconds'),
        ));

        $this->app->bind(UnipotenceHttpAdapter::class, fn (): UnipotenceHttpAdapter => new UnipotenceHttpAdapter(
            maxKeyLength: (int) config('unipotence.max_key_length'),
            maxResponseBytes: (int) config('unipotence.max_response_bytes'),
            replayHeaders: (array) config('unipotence.replay_headers'),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([PruneUnipotences::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('unipotence:prune')
                    ->daily()
                    ->withoutOverlapping();
            });
        }

        // Composer package discovery and clearing a stale config cache are metadata-only
        // maintenance commands. They must remain usable when a previous config cache
        // lacks newly added Unipotence settings. Real requests, queue workers, the
        // scheduler, tests, and config:cache still validate strictly and fail closed.
        if ($this->app->runningInConsole()
            && in_array($_SERVER['argv'][1] ?? null, ['package:discover', 'config:clear', 'optimize:clear'], true)) {
            return;
        }

        $leaseSeconds = (int) config('unipotence.lease_seconds');
        $maxRequestSeconds = (int) config('unipotence.max_request_seconds');
        $maxKeyLength = (int) config('unipotence.max_key_length');
        $maxResponseBytes = (int) config('unipotence.max_response_bytes');
        $retentionDays = (int) config('unipotence.retention_days');
        $indeterminateRetentionDays = (int) config('unipotence.indeterminate_retention_days');
        $connection = trim((string) config('unipotence.connection'));
        $invalid = [];

        if ($connection === '') {
            $invalid[] = 'UNIPOTENCE_DB_CONNECTION (or DB_CONNECTION) must not be empty';
        }
        if ($maxRequestSeconds < 1) {
            $invalid[] = 'UNIPOTENCE_MAX_REQUEST_SECONDS must be >= 1';
        }
        if ($leaseSeconds <= $maxRequestSeconds) {
            $invalid[] = 'UNIPOTENCE_LEASE_SECONDS must exceed UNIPOTENCE_MAX_REQUEST_SECONDS';
        }
        if ($maxKeyLength < 1 || $maxKeyLength > 255) {
            $invalid[] = 'UNIPOTENCE_MAX_KEY_LENGTH must be between 1 and 255 (got '.$maxKeyLength.')';
        }
        if ($maxResponseBytes < 1) {
            $invalid[] = 'UNIPOTENCE_MAX_RESPONSE_BYTES must be >= 1 (got '.$maxResponseBytes.')';
        }
        if ($retentionDays < 1) {
            $invalid[] = 'UNIPOTENCE_RETENTION_DAYS must be >= 1 (got '.$retentionDays.')';
        }
        if ($indeterminateRetentionDays < 1) {
            $invalid[] = 'UNIPOTENCE_INDETERMINATE_RETENTION_DAYS must be >= 1 (got '.$indeterminateRetentionDays.')';
        }

        if ($invalid !== []) {
            throw new \InvalidArgumentException(
                'Invalid Unipotence configuration: '.implode('; ', $invalid).
                '. Review .env/.env.testing and clear stale cached configuration with php artisan config:clear.',
            );
        }
    }
}
