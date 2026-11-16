<?php

namespace Tests\Unit\Platform;

use App\Support\Testing\SafeTestDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SafeTestDatabaseTest extends TestCase
{
    public function test_only_local_disposable_mysql_database_names_pass(): void
    {
        foreach (['planner_test', 'planner_testing'] as $name) {
            SafeTestDatabase::assertConfiguration('testing', 'mysql', [
                'database' => $name, 'host' => '127.0.0.1', 'url' => null,
            ]);
        }
        $this->addToAssertionCount(2);
    }

    /** @dataProvider unsafeEnvironments */
    #[DataProvider('unsafeEnvironments')]
    public function test_all_unsafe_targets_are_rejected(string $env, string $driver, array $mysql): void
    {
        $this->expectException(\RuntimeException::class);
        SafeTestDatabase::assertConfiguration($env, $driver, $mysql);
    }

    public static function unsafeEnvironments(): array
    {
        $ok = ['database' => 'planner_test', 'host' => '127.0.0.1', 'url' => null];

        return [
            'production environment' => ['production', 'mysql', $ok],
            'local environment' => ['local', 'mysql', $ok],
            'sqlite connection' => ['testing', 'sqlite', $ok],
            'production database' => ['testing', 'mysql', ['database' => 'planner', 'host' => '127.0.0.1']],
            'test in production-named db' => ['testing', 'mysql', ['database' => 'planner_prod_test', 'host' => '127.0.0.1']],
            'remote database' => ['testing', 'mysql', ['database' => 'planner_test', 'host' => 'production.example.org']],
            'DB_URL override' => ['testing', 'mysql', ['database' => 'planner_test', 'host' => '127.0.0.1', 'url' => 'mysql://example']],
        ];
    }
}
