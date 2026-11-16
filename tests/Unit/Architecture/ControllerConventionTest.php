<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/** Guard against action-heavy controllers as the platform grows. */
final class ControllerConventionTest extends TestCase
{
    public function test_every_application_controller_uses_only_resource_methods_or_single_action_invoke(): void
    {
        $permitted = ['index', 'store', 'update', 'destroy', 'show', 'edit', '__invoke'];
        $count = 0;
        $directory = dirname(__DIR__, 3).'/app/Http/Controllers';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getRealPath());
            preg_match_all('/\bpublic\s+function\s+([A-Za-z_][A-Za-z_0-9]*)\s*\(/', $source, $matches);
            $methods = $matches[1];
            foreach ($methods as $method) {
                self::assertContains($method, $permitted, "{$file->getFilename()} exposes disallowed action {$method}");
            }
            if (in_array('__invoke', $methods, true)) {
                self::assertSame(['__invoke'], $methods, "{$file->getFilename()} must be a single-action controller");
            }
            $count++;
        }
        self::assertGreaterThanOrEqual(40, $count, 'The controller directory may not be fully inspected');
    }
}
