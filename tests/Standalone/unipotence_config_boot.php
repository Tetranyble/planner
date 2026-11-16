<?php

// Dependency-free regression for service-provider boot configuration. Run with:
// php tests/Standalone/unipotence_config_boot.php

namespace Illuminate\Support {
    class ServiceProvider
    {
        public function __construct(protected object $app) {}

        protected function loadMigrationsFrom(string $path): void {}

        protected function commands(array $commands): void {}

        protected function callAfterResolving(string $abstract, callable $callback): void {}
    }
}

namespace {
    function config(string $key): mixed
    {
        return $GLOBALS['unipotence_config'][$key] ?? null;
    }

    final class StandaloneApp
    {
        public function __construct(private readonly bool $console) {}

        public function runningInConsole(): bool
        {
            return $this->console;
        }
    }

    require __DIR__.'/../../modules/Unipotence/Infrastructure/UnipotenceServiceProvider.php';

    use Modules\Unipotence\Infrastructure\UnipotenceServiceProvider;

    $defaults = [
        'unipotence.connection' => 'mysql',
        'unipotence.lease_seconds' => 300,
        'unipotence.max_request_seconds' => 120,
        'unipotence.max_key_length' => 255,
        'unipotence.max_response_bytes' => 1048576,
        'unipotence.retention_days' => 30,
        'unipotence.indeterminate_retention_days' => 90,
    ];

    $checks = 0;
    $boot = static function (bool $console, string $command, array $overrides = []) use ($defaults): void {
        $GLOBALS['unipotence_config'] = array_replace($defaults, $overrides);
        $_SERVER['argv'] = ['artisan', $command];
        (new UnipotenceServiceProvider(new StandaloneApp($console)))->boot();
    };
    $failsWith = static function (bool $console, string $command, array $overrides, string $expected) use ($boot, &$checks): void {
        try {
            $boot($console, $command, $overrides);
        } catch (InvalidArgumentException $e) {
            if (! str_contains($e->getMessage(), $expected)) {
                throw new RuntimeException('Wrong diagnostic: '.$e->getMessage());
            }
            $checks++;

            return;
        }
        throw new RuntimeException('Expected boot failure containing: '.$expected);
    };

    $boot(true, 'package:discover');
    $checks++;
    $boot(false, '', []);
    $checks++;
    $boot(true, 'config:cache');
    $checks++;
    $boot(true, 'package:discover', ['unipotence.max_key_length' => 0]);
    $checks++;
    $boot(true, 'config:clear', ['unipotence.max_response_bytes' => 0]);
    $checks++;
    $boot(true, 'optimize:clear', ['unipotence.retention_days' => 0]);
    $checks++;
    $failsWith(true, 'config:cache', ['unipotence.max_key_length' => 0], 'UNIPOTENCE_MAX_KEY_LENGTH');
    $failsWith(false, '', ['unipotence.retention_days' => 0], 'UNIPOTENCE_RETENTION_DAYS');
    $failsWith(true, 'test', ['unipotence.max_response_bytes' => 0], 'UNIPOTENCE_MAX_RESPONSE_BYTES');
    $failsWith(true, 'queue:work', ['unipotence.connection' => ''], 'UNIPOTENCE_DB_CONNECTION');
    $failsWith(true, 'test', ['unipotence.lease_seconds' => 90], 'UNIPOTENCE_LEASE_SECONDS');

    echo "Passed {$checks} Unipotence config-boot checks.\n";
}
