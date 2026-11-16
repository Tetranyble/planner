<?php

/** Zero-dependency smoke tests for environments without Composer vendor. */
$root = dirname(__DIR__);
spl_autoload_register(function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $path = $root.'/app/'.str_replace('\\', '/', substr($class, 4)).'.php';
        if (is_file($path)) {
            require $path;
        }
    }
});
use App\Domain\Publishing\Delivery\SimulationHarness;

$checks = 0;
foreach (['facebook', 'instagram', 'linkedin', 'x', 'tiktok', 'youtube'] as $provider) {
    foreach (SimulationHarness::SCENARIOS as $scenario) {
        $result = SimulationHarness::run($provider, $scenario);
        if ($result['network_requests'] !== 0 || $result['database_writes'] !== 0 || $result['gate']['may_dispatch'] !== false) {
            throw new RuntimeException('Unsafe simulation');
        }
        $checks++;
    }
}
echo "Publication simulation smoke assertions: $checks passed\n";
