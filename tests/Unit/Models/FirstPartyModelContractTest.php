<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;

/**
 * Pure source contracts: no Laravel kernel, DB, migrations, HTTP clients or Vite.
 * Keep these tests cheap so model regressions are visible even on low-memory CI.
 */
final class FirstPartyModelContractTest extends TestCase
{
    private const ROOT = __DIR__.'/../../..';

    public function test_each_first_party_model_has_an_explicit_fillable_allowlist_and_factory(): void
    {
        $manifest = require self::ROOT.'/tests/Support/ModelSchemaManifest.php';
        $files = glob(self::ROOT.'/app/Models/*.php') ?: [];
        $found = [];

        foreach ($files as $file) {
            $found[] = basename($file, '.php');
        }

        $found[] = 'Unipotence';
        sort($found);
        $documented = array_keys($manifest);
        sort($documented);
        self::assertSame($documented, $found, 'Add every first-party model to the schema contract.');

        foreach ($documented as $model) {
            $sourceFile = $model === 'Unipotence'
                ? self::ROOT.'/modules/Unipotence/Infrastructure/Persistence/Models/Unipotence.php'
                : self::ROOT.'/app/Models/'.$model.'.php';
            $source = file_get_contents($sourceFile);

            self::assertIsString($source);
            self::assertMatchesRegularExpression('/protected\s+\$fillable\s*=\s*\[/m', $source, $model.' must declare $fillable.');
            self::assertDoesNotMatchRegularExpression('/protected\s+\$guarded\b/m', $source, $model.' must not use $guarded.');
            if ($model === 'PlannerOAuthUser') {
                self::assertStringContainsString('extends User', $source); // Inherits HasFactory.
            } else {
                self::assertStringContainsString('HasFactory', $source, $model.' must support Laravel factories.');
            }
            $factoryFile = self::ROOT.'/database/factories/'.$model.'Factory.php';
            self::assertFileExists($factoryFile, $model.' requires a factory.');
            self::assertStringContainsString('function definition()', file_get_contents($factoryFile));

            if (in_array('uuid', $manifest[$model]['columns'], true)) {
                self::assertStringContainsString('Uuidable', $source, $model.' has a UUID column and must use Uuidable.');
            }
        }
    }

    public function test_declared_foreign_key_relationships_remain_available(): void
    {
        $manifest = require self::ROOT.'/tests/Support/ModelSchemaManifest.php';

        foreach ($manifest as $name => $details) {
            if ($name === 'Unipotence') {
                continue;
            }

            $source = file_get_contents(self::ROOT.'/app/Models/'.$name.'.php');
            foreach ($details['relations'] as $method => [$target, $foreignKey]) {
                self::assertMatchesRegularExpression(
                    '/function\s+'.preg_quote($method, '/').'\s*\(/',
                    $source,
                    "$name::$method disappeared."
                );
                self::assertStringContainsString("$target::class, '$foreignKey'", $source,
                    "$name::$method must reference the correct model and database FK.");
            }
        }
    }

    public function test_uuidable_trait_retains_creation_hook_and_missing_uuid_fallback(): void
    {
        $path = self::ROOT.'/app/Concerns/Uuidable.php';
        $trait = file_get_contents($path);
        self::assertStringContainsString("mergeFillable(['uuid'])", $trait);
        self::assertStringContainsString('bootUuidable', $trait);
        self::assertStringContainsString('creating', $trait);
        self::assertStringContainsString('??=', $trait);
    }
}
