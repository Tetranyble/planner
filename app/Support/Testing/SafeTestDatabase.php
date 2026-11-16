<?php

namespace App\Support\Testing;

use Illuminate\Foundation\Application;

/**
 * Fail closed BEFORE running RefreshDatabase, migrate:fresh, or rollback.
 * This is for disposable local MySQL databases only, never production.
 */
final class SafeTestDatabase
{
    public static function assertSafe(Application $app): void
    {
        $config = $app['config'];
        self::assertConfiguration(
            $app->environment(),
            (string) $config->get('database.default'),
            (array) $config->get('database.connections.mysql', []),
        );
    }

    public static function assertConfiguration(string $environment, string $driver, array $mysql): void
    {
        $database = (string) ($mysql['database'] ?? '');
        $host = (string) ($mysql['host'] ?? '');
        $url = $mysql['url'] ?? null;
        if ($environment !== 'testing' || $driver !== 'mysql'
            || ! preg_match('/(?:^|_)test(?:ing)?$/i', $database)
            || preg_match('/prod|production|live/i', $database)
            || ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || (is_string($url) && trim($url) !== '')
            || (is_array($url) && count($url) > 0)) {
            throw new \RuntimeException(
                'Unsafe test database configuration. Require APP_ENV=testing, local MySQL, '
                .'a dedicated *_test or *_testing database, and empty DB_URL. '
                .'Refusing database migrations.'
            );
        }
    }
}
