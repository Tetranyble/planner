<?php

namespace Tests\Unit\Integrations;

use App\Services\Integrations\PlannerMcpReader;
use PHPUnit\Framework\TestCase;

final class PlannerMcpReaderContractTest extends TestCase
{
    public function test_reader_has_exactly_five_public_read_methods_and_no_mutations(): void
    {
        $methods = get_class_methods(PlannerMcpReader::class);
        self::assertContains('read', $methods);
        self::assertNotContains('publish', $methods);
        self::assertNotContains('approve', $methods);
        self::assertNotContains('schedule', $methods);
    }
}
