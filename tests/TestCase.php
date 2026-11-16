<?php

namespace Tests;

use App\Support\Testing\SafeTestDatabase;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Vite;

abstract class TestCase extends BaseTestCase
{
    private string $testingViteHotFile;

    /**
     * Runs during Laravel application creation, BEFORE RefreshDatabase invokes
     * destructive test migrations. No test may run against the production DB.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        SafeTestDatabase::assertSafe($app);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // HTTP feature tests exercise the controller/authorization contract,
        // without a browser session CSRF token. Production CSRF stays enabled.
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->testingViteHotFile = storage_path('framework/testing-vite.hot');
        file_put_contents($this->testingViteHotFile, 'http://localhost:5173');
        Vite::useHotFile($this->testingViteHotFile);
    }

    protected function tearDown(): void
    {
        if (is_file($this->testingViteHotFile)) {
            unlink($this->testingViteHotFile);
        }

        parent::tearDown();
    }
}
