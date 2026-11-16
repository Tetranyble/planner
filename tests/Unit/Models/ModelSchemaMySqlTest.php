<?php

namespace Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * One Laravel boot and one migration pass, not hundreds of separate test cases.
 * Only a disposable MySQL test schema is allowed by SafeTestDatabase.
 */
final class ModelSchemaMySqlTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_documented_model_column_is_present_in_mysql(): void
    {
        self::assertSame('mysql', DB::connection()->getDriverName());
        $manifest = require dirname(__DIR__, 3).'/tests/Support/ModelSchemaManifest.php';
        $seen = [];

        foreach ($manifest as $model => $details) {
            $table = $details['table'];
            if (! isset($seen[$table])) {
                self::assertTrue(Schema::hasTable($table), "$model requires table $table");
                $seen[$table] = array_flip(Schema::getColumnListing($table));
            }

            foreach ($details['columns'] as $column) {
                self::assertArrayHasKey($column, $seen[$table], "$model: $table.$column is missing");
            }
        }
    }
}
