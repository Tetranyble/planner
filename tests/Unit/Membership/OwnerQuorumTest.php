<?php

namespace Tests\Unit\Membership;

use App\Domain\Membership\OwnerQuorum;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class OwnerQuorumTest extends TestCase
{
    public function test_sole_owner_cannot_be_revoked_or_demoted(): void
    {
        try {
            OwnerQuorum::ensureSurvives(true, true, 1);
            $this->fail('A sole owner must not be removable.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_other_transitions_are_allowed(): void
    {
        OwnerQuorum::ensureSurvives(true, false, 1);
        OwnerQuorum::ensureSurvives(false, true, 1);
        OwnerQuorum::ensureSurvives(true, true, 2);
        $this->addToAssertionCount(3);
    }
}
