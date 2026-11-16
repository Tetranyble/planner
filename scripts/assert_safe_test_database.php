<?php

use App\Support\Testing\SafeTestDatabase;
use Illuminate\Contracts\Console\Kernel;

/**
 * Pre-migration safety gate: evaluates the resolved Laravel configuration,
 * including .env.testing and environment overrides; shell env alone is not enough.
 */
require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    SafeTestDatabase::assertSafe($app);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}

echo 'Safe disposable MySQL testing database: '.config('database.connections.mysql.database').PHP_EOL;
