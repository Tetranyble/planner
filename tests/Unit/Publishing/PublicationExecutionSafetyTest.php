<?php

namespace Tests\Unit\Publishing;

use PHPUnit\Framework\TestCase;

final class PublicationExecutionSafetyTest extends TestCase
{
    public function test_production_release_is_never_enabled_by_default(): void
    {
        // The service class has no static fallback that enables live delivery.
        // Laravel-level config and environment tests run in feature/integration suites.
        $source = file_get_contents(__DIR__.'/../../../app/Services/Publishing/ExecutePublicationPlans.php');
        self::assertStringContainsString("config('planner.text_dispatch_enabled',false)", $source);
        self::assertStringContainsString("config('planner.text_dispatch_provider_approved',false)", $source);
        self::assertStringContainsString("config('planner.text_dispatch_released',false)", $source);
        self::assertStringContainsString("app()->environment('production')", $source);
        self::assertStringContainsString('attemptIsFenced', $source);
        self::assertStringContainsString('CurrentOrganisation', $source);
        self::assertStringNotContainsString('TextDispatchAuthorization(true,true,true,true,true,true,true,true,true,true)', $source);
    }
}
