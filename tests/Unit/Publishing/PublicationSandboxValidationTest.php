<?php

namespace Tests\Unit\Publishing;

use App\Repository\Contracts\PublicationSandboxRepositoryContract;
use App\Services\Publishing\RunPublicationSandbox;
use PHPUnit\Framework\TestCase;

final class PublicationSandboxValidationTest extends TestCase
{
    public function test_rejects_invalid_keys_before_database_access(): void
    {
        $repo = $this->createMock(PublicationSandboxRepositoryContract::class);
        $repo->expects($this->never())->method('heldJob');
        $this->expectException(\InvalidArgumentException::class);
        (new RunPublicationSandbox($repo))->enqueue(1, 2, 3, 'bad key!', 'facebook', 'timeout');
    }

    public function test_rejects_unsupported_provider_before_database_access(): void
    {
        $repo = $this->createMock(PublicationSandboxRepositoryContract::class);
        $repo->expects($this->never())->method('heldJob');
        $this->expectException(\InvalidArgumentException::class);
        (new RunPublicationSandbox($repo))->enqueue(1, 2, 3, 'test:operation:01', 'unknown', 'timeout');
    }

    public function test_rejects_unrecognized_scenario_before_database_access(): void
    {
        $repo = $this->createMock(PublicationSandboxRepositoryContract::class);
        $repo->expects($this->never())->method('heldJob');
        $this->expectException(\InvalidArgumentException::class);
        (new RunPublicationSandbox($repo))->enqueue(1, 2, 3, 'test:operation:01', 'facebook', 'live');
    }
}
